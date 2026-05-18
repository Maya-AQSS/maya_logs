<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\ArchivedLogDto;
use App\Events\ArchivedLogFieldsWereUpdated;
use App\Events\ArchivedLogWasDeleted;
use App\Events\LogWasArchived;
use App\Models\ArchivedLog;
use App\Repositories\Contracts\ArchivedLogRepositoryInterface;
use App\Services\Contracts\ArchivedLogServiceInterface;
use Maya\Http\Pagination\PaginatedDto;
use Maya\Messaging\Publishers\ResilientLogPublisher;
use Throwable;

class ArchivedLogService implements ArchivedLogServiceInterface
{
    public function __construct(
        private ArchivedLogRepositoryInterface $archivedLogRepository,
        private ResilientLogPublisher $resilientLogPublisher,
    ) {}

    private function messagingAppSlug(): string
    {
        return (string) config('messaging.app');
    }

    public function paginate(int $perPage = 15): PaginatedDto
    {
        return PaginatedDto::fromPaginator(
            $this->archivedLogRepository->paginate($perPage),
            static fn (ArchivedLog $m) => ArchivedLogDto::fromModel($m),
        );
    }

    public function searchAndFilter(
        ?array $severities,
        ?int $applicationId,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $sortBy,
        string $sortDir,
        int $perPage = 15
    ): PaginatedDto {
        return PaginatedDto::fromPaginator(
            $this->archivedLogRepository->searchAndFilter(
                $severities,
                $applicationId,
                $dateFrom,
                $dateTo,
                $sortBy,
                $sortDir,
                $perPage
            ),
            static fn (ArchivedLog $m) => ArchivedLogDto::fromModel($m),
        );
    }

    public function findOrFail(int $id): ArchivedLogDto
    {
        return ArchivedLogDto::fromModel($this->findModelOrFail($id));
    }

    public function findModelOrFail(int $id): ArchivedLog
    {
        try {
            return $this->archivedLogRepository->findOrFail($id);
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                'LAR-LOG-004',
                ['archived_log_id' => $id],
                $this->messagingAppSlug(),
            );
            throw $e;
        }
    }

    /**
     * Actualiza los campos de un log archivado.
     *
     * El actor en auditoría es `archived_by_id` (subject JWT), coherente con {@see ArchivedLogPolicy}.
     * Si los valores ya coinciden con lo enviado (no-op / doble envío), no se persiste ni se emite
     * {@see ArchivedLogFieldsWereUpdated} (evita duplicar filas en maya.audit).
     */
    public function updateArchivedFields(ArchivedLog $archivedLog, array $fields): void
    {
        try {
            $sanitized = array_map(
                static fn ($value) => is_string($value) ? (blank($value) ? null : trim($value)) : $value,
                $fields
            );

            if ($sanitized === [] || ! $this->archivedLogSanitizedDiffersFromModel($archivedLog, $sanitized)) {
                return;
            }

            $previousValue = [];
            foreach (array_keys($sanitized) as $key) {
                $previousValue[$key] = $archivedLog->getAttribute($key);
            }

            $this->archivedLogRepository->updateArchivedFields($archivedLog, $sanitized);

            ArchivedLogFieldsWereUpdated::dispatch(
                $archivedLog->id,
                (string) $archivedLog->archived_by_id,
                $previousValue,
                $sanitized,
            );
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                'LAR-LOG-001',
                ['archived_log_id' => $archivedLog->id],
                $this->messagingAppSlug(),
            );
            throw $e;
        }
    }

    /**
     * Elimina un log archivado.
     *
     * Solo se emite {@see ArchivedLogWasDeleted} si el soft delete se aplicó (evita duplicar audit
     * si {@see ArchivedLogRepositoryInterface::delete} no modifica filas).
     */
    public function delete(ArchivedLog $archivedLog): void
    {
        try {
            $archivedLogId = $archivedLog->id;
            $archivedByUserId = (string) $archivedLog->archived_by_id;

            if (! $this->archivedLogRepository->delete($archivedLog)) {
                return;
            }

            ArchivedLogWasDeleted::dispatch($archivedLogId, $archivedByUserId);
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                'LAR-LOG-002',
                ['archived_log_id' => $archivedLog->id],
                $this->messagingAppSlug(),
            );
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $sanitized
     */
    private function archivedLogSanitizedDiffersFromModel(ArchivedLog $archivedLog, array $sanitized): bool
    {
        foreach ($sanitized as $key => $value) {
            if ($archivedLog->getAttribute($key) != $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Archiva un log por su id.
     *
     * Solo se emite {@see LogWasArchived} cuando el repositorio crea un registro nuevo.
     * Si devuelve uno ya existente (huella duplicada o segunda petición concurrente), no se
     * vuelve a publicar a maya.audit (evita filas duplicadas con el mismo `archived_log`).
     */
    public function archiveFromLogId(int $logId, string $archivedByUserId): ArchivedLog
    {
        try {
            $archivedLog = $this->archivedLogRepository->archiveFromLogId($logId, $archivedByUserId);
            if ($archivedLog->wasRecentlyCreated) {
                LogWasArchived::dispatch($archivedLog, $archivedByUserId);
            }

            return $archivedLog;
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                'LAR-LOG-003',
                ['log_id' => $logId],
                $this->messagingAppSlug(),
            );
            throw $e;
        }
    }
}

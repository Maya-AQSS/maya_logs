<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ArchivedLog;
use App\Models\Log;
use App\Policies\ArchivedLogPolicy;
use App\Repositories\Contracts\ArchivedLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ArchivedLogRepository implements ArchivedLogRepositoryInterface
{
    private const SORT_DIRECTIONS = ['asc', 'desc'];

    private const ALLOWED_ARCHIVED_FIELDS = ['resolved', 'error_code_id', 'internal_notes', 'description', 'url_tutorial'];

    /**
     * Devuelve una página de logs archivados.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return ArchivedLog::query()
            ->withStandardRelations()
            ->latest('archived_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Busca y filtra logs archivados por diferentes criterios:
     * - tipo de severidad de error
     * - si tiene tutorial o no
     */
    public function searchAndFilter(
        ?array $severities,
        ?int $applicationId,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $sortBy,
        string $sortDir,
        int $perPage = 15
    ): LengthAwarePaginator {
        $validatedSortDirection = in_array($sortDir, self::SORT_DIRECTIONS, true) ? $sortDir : 'asc';

        $query = ArchivedLog::query()
            ->withStandardRelations()
            ->when($severities !== null && $severities !== [], fn ($q) => $q->whereIn('severity', $severities))
            ->when($applicationId !== null, fn ($q) => $q->where('application_id', $applicationId))
            ->when($dateFrom !== null, fn ($q) => $q->where('archived_at', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($q) => $q->where('archived_at', '<=', $dateTo));

        $query = match ($sortBy) {
            'archived_at' => $query
                ->orderBy('archived_at', $validatedSortDirection)
                ->orderByDesc('id'),
            'severity' => $this->applySeverityRankOrder($query, $validatedSortDirection),
            default => $query
                ->orderBy('archived_at', 'desc')
                ->orderByDesc('id'),
        };

        return $query
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Orden de negocio: critical → high → medium → low → other (ASC).
     * DESC invierte ese ranking.
     */
    private function applySeverityRankOrder(Builder $query, string $direction): Builder
    {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $query->orderByRaw(
            'CASE severity WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 WHEN ? THEN 4 WHEN ? THEN 5 ELSE 99 END '.$dir,
            ['critical', 'high', 'medium', 'low', 'other']
        );
        $query->orderByDesc('archived_at');
        $query->orderByDesc('id');

        return $query;
    }

    /**
     * Busca un log archivado por su id.
     */
    public function findOrFail(int $id): ArchivedLog
    {
        return ArchivedLog::query()
            ->withStandardRelations()
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $fields
     *
     * No valida actor; debe haberse pasado {@see ArchivedLogPolicy} (p. ej. vía `authorize` en el controlador).
     */
    public function updateArchivedFields(ArchivedLog $archivedLog, array $fields): void
    {
        $archivedLog->update(array_intersect_key($fields, array_flip(self::ALLOWED_ARCHIVED_FIELDS)));
    }

    /**
     * Soft delete. La autorización la define {@see ArchivedLogPolicy}.
     */
    public function delete(ArchivedLog $archivedLog): bool
    {
        return (bool) $archivedLog->delete();
    }

    /**
     * Archiva un log por su id.
     */
    public function archiveFromLogId(int $logId, string $archivedByUserId): ArchivedLog
    {
        return DB::transaction(function () use ($logId, $archivedByUserId): ArchivedLog {
            $log = Log::query()
                ->with(['errorCode'])
                ->whereKey($logId)
                ->firstOrFail();

            $existingArchived = ArchivedLog::query()
                ->where('application_id', $log->application_id)
                ->whereRaw('error_code_id IS NOT DISTINCT FROM ?', [$log->error_code_id])
                ->where('severity', $log->severity)
                ->where('message', $log->message)
                ->orderByDesc('archived_at')
                ->orderByDesc('id')
                ->first();

            if ($existingArchived !== null) {
                return $existingArchived;
            }

            $archivedLog = ArchivedLog::query()->create([
                'application_id' => (int) $log->application_id,
                'archived_by_id' => $archivedByUserId,
                'error_code_id' => $log->error_code_id,
                'severity' => $log->severity,
                'message' => $log->message,
                'metadata' => $log->metadata,
                'description' => null,
                'url_tutorial' => null,
                'original_created_at' => $log->created_at,
                'archived_at' => now(),
            ]);

            return $archivedLog;
        });
    }
}

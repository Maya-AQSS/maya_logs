<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ArchivedLog;
use App\Models\Log;
use App\Policies\ArchivedLogPolicy;
use App\Repositories\Contracts\ArchivedLogRepositoryInterface;
use App\Services\ArchivedFieldsValidator;
use App\Services\SeverityRankingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ArchivedLogRepository implements ArchivedLogRepositoryInterface
{
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
        // Defensa: coercer a una dirección válida (el FormRequest ya valida
        // sort_dir, pero el repo no debe lanzar si llega un valor arbitrario).
        $sortDir = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        $query = ArchivedLog::query()
            ->withStandardRelations()
            ->when($severities !== null && $severities !== [], fn ($q) => $q->whereIn('severity', $severities))
            ->when($applicationId !== null, fn ($q) => $q->where('application_id', $applicationId))
            ->when($dateFrom !== null, fn ($q) => $q->where('archived_at', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($q) => $q->where('archived_at', '<=', $dateTo));

        $query = match ($sortBy) {
            'archived_at' => $query
                ->orderBy('archived_at', $sortDir)
                ->orderByDesc('id'),
            'severity' => $this->applySeverityRankOrder($query, $sortDir),
            'application' => $query
                ->join('applications', 'archived_logs.application_id', '=', 'applications.id')
                ->orderBy('applications.name', $sortDir)
                ->orderByDesc('archived_logs.id')
                ->select('archived_logs.*'),
            'original_created_at' => $query
                ->orderBy('original_created_at', $sortDir)
                ->orderByDesc('id'),
            default => $query
                ->orderBy('archived_at', 'desc')
                ->orderByDesc('id'),
        };

        return $query
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Apply severity ranking to the query.
     *
     * La regla de dominio (jerarquía de severidad) la aporta {@see SeverityRankingService};
     * la construcción del SQL (CASE/ORDER BY) vive en el scope del modelo
     * {@see ArchivedLog::scopeOrderBySeverityRank()}, manteniendo Eloquent fuera del Service.
     */
    private function applySeverityRankOrder(Builder $query, string $direction): Builder
    {
        $severityHierarchy = app(SeverityRankingService::class)->severityHierarchy();

        return $query->orderBySeverityRank($severityHierarchy, $direction);
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
     * Assumes fields have been validated and filtered by the Service layer.
     */
    public function updateArchivedFields(ArchivedLog $archivedLog, array $fields): void
    {
        // Defensa en profundidad: solo persistir campos de la whitelist, aunque
        // el FormRequest ya los filtre (evita mass-assignment si se llama directo).
        $archivedLog->update((new ArchivedFieldsValidator)->filterAllowed($fields));
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

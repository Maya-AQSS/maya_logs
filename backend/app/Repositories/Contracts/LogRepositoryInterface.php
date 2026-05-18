<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Log;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface LogRepositoryInterface
{
    public function paginate(int $perPage = 25): LengthAwarePaginator;

    public function findOrFail(int $id): Log;

    /**
     * SSE: últimos logs para streaming en tiempo real.
     */
    public function latestForStream(int $limit = 10): Collection;

    /**
     * Buscar y filtrar.
     */
    public function searchAndFilter(
        ?string $search,
        ?array $severity,
        ?int $applicationId,
        ?string $archived,
        ?string $resolved,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $sortBy,
        ?string $sortDir,
        int $perPage = 25
    ): LengthAwarePaginator;

    /**
     * Counts grouped by severity y resolved.
     *
     * @return array<string,array{resolved:int,unresolved:int,total:int}>
     */
    public function severityResolvedCounts(bool $includeArchived = false): array;

    /**
     * Total logs count (COUNT(*)) for selected scope.
     */
    public function logsCount(bool $includeArchived = false): int;

    /**
     * Conteos de logs agrupados por aplicación (nombre desde join con applications).
     *
     * @return array<int, array{application_id: int, name: string, total: int}>
     */
    public function applicationTotals(bool $includeArchived = true): array;

    /**
     * Devuelve el id de ArchivedLog equivalente al log o null si no está archivado.
     */
    public function archivedLogIdFor(int $logId): ?int;

    /**
     * Marca el log como resuelto (resolved = true).
     */
    public function resolved(int $logId): void;
}

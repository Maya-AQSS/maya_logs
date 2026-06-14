<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Dtos\LogDto;
use App\Dtos\LogFilterDto;
use App\Models\Log;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
     * Buscar y filtrar con paginación server-side.
     */
    public function searchAndFilter(LogFilterDto $filter): LengthAwarePaginator;

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
     * Carga el log (con relaciones) y resuelve el id del ArchivedLog equivalente
     * en una sola ronda de base de datos.
     *
     * @return array{log: Log, archived_log_id: int|null}
     *
     * @throws ModelNotFoundException
     */
    public function findOrFailWithArchivedLogId(int $id): array;

    /**
     * Marca el log como resuelto (resolved = true).
     */
    public function resolved(int $logId): void;

    /**
     * Devuelve los DTOs más recientes para streaming en tiempo real.
     *
     * @return Collection<int, LogDto>
     */
    public function streamPayloadDtos(int $limit = 10): Collection;

    /**
     * Indica si hay una transacción activa en la BD.
     */
    public function isInTransaction(): bool;
}

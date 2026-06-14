<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Dtos\LogDto;
use App\Dtos\LogFilterDto;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Maya\Http\Pagination\PaginatedDto;

interface LogServiceInterface
{
    /**
     * @return PaginatedDto<LogDto>
     */
    public function paginate(int $perPage = 25): PaginatedDto;

    public function findOrFail(int $id): LogDto;

    /**
     * Carga el log y el id del archived_log equivalente en una sola query,
     * eliminando el N+1 del endpoint show().
     *
     * @return array{dto: LogDto, archived_log_id: int|null}
     *
     * @throws ModelNotFoundException
     */
    public function findForShow(int $id): array;

    /**
     * Prepare SSE payload.
     */
    public function streamPayload(int $limit = 10): array;

    /**
     * @return PaginatedDto<LogDto>
     */
    public function searchAndFilter(LogFilterDto $filter): PaginatedDto;

    /**
     * @return array<int,array{key:string,totalCount:int,resolvedCount:int,unresolvedCount:int}>
     */
    public function dashboardSeverityCards(): array;

    /**
     * @return array<int, array{application_id: int, name: string, total: int}>
     */
    public function dashboardApplicationTotals(): array;

    public function archivedLogIdFor(int $logId): ?int;

    /**
     * Marca un log como resuelto. Publica a maya.audit con el actor JWT.
     */
    public function resolved(int $logId, string $actorUserId): void;
}

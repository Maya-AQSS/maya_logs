<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Dtos\LogDto;
use Maya\Http\Pagination\PaginatedDto;

interface LogServiceInterface
{
    /**
     * @return PaginatedDto<LogDto>
     */
    public function paginate(int $perPage = 25): PaginatedDto;

    public function findOrFail(int $id): LogDto;

    /**
     * Prepare SSE payload.
     */
    public function streamPayload(int $limit = 10): array;

    /**
     * @return PaginatedDto<LogDto>
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
    ): PaginatedDto;

    /**
     * @return array<int,array{key:string,totalCount:int,resolvedCount:int,unresolvedCount:int}>
     */
    public function dashboardSeverityCards(): array;

    /**
     * @return array<int, array{application_id: int, name: string, total: int}>
     */
    public function dashboardApplicationTotals(): array;

    public function archivedLogIdFor(int $logId): ?int;

    public function resolved(int $logId): void;
}

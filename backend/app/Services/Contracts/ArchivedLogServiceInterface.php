<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Dtos\ArchivedLogDto;
use Maya\Http\Pagination\PaginatedDto;

interface ArchivedLogServiceInterface
{
    /**
     * @return PaginatedDto<ArchivedLogDto>
     */
    public function paginate(int $perPage = 15): PaginatedDto;

    /**
     * @return PaginatedDto<ArchivedLogDto>
     */
    public function searchAndFilter(
        ?array $severities,
        ?int $applicationId,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $sortBy,
        string $sortDir,
        int $perPage = 15
    ): PaginatedDto;

    public function findOrFail(int $id): ArchivedLogDto;

    /**
     * @param  array<string, mixed>  $fields
     */
    public function updateArchivedFields(int $id, array $fields): ArchivedLogDto;

    public function delete(int $id): void;

    public function archiveFromLogId(int $logId, string $archivedByUserId): ArchivedLogDto;

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function validateAndFilterFields(array $fields): array;
}

<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Dtos\ArchivedLogDto;
use App\Models\ArchivedLog;
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
     * Model lookup for the controller's policy gate (authorize uses the Eloquent
     * instance). Kept separate from {@see self::findOrFail()} which returns a DTO.
     */
    public function findModelOrFail(int $id): ArchivedLog;

    /**
     * @param  array<string, mixed>  $fields
     */
    public function updateArchivedFields(ArchivedLog $archivedLog, array $fields): void;

    public function delete(ArchivedLog $archivedLog): void;

    public function archiveFromLogId(int $logId, string $archivedByUserId): ArchivedLog;
}

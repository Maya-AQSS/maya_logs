<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Dtos\ErrorCodeDto;
use App\Models\ErrorCode;
use Maya\Http\Pagination\PaginatedDto;

interface ErrorCodeServiceInterface
{
    /**
     * @return PaginatedDto<ErrorCodeDto>
     */
    public function paginate(int $perPage = 15): PaginatedDto;

    /**
     * @return PaginatedDto<ErrorCodeDto>
     */
    public function searchAndFilter(
        ?string $search,
        ?int $filterApp,
        ?string $sortBy = null,
        ?string $sortDir = null,
        int $perPage = 15
    ): PaginatedDto;

    public function findOrFail(int $id): ErrorCodeDto;

    /**
     * Model lookup for the controller's policy gate (authorize uses the Eloquent
     * instance). Kept separate from {@see self::findOrFail()} which returns a DTO.
     */
    public function findModelOrFail(int $id): ErrorCode;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ErrorCodeDto;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): ErrorCodeDto;

    public function delete(int $id): void;
}

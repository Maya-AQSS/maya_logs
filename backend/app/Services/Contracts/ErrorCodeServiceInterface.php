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
        int $perPage = 15
    ): PaginatedDto;

    public function findOrFail(int $id): ErrorCodeDto;

    /**
     * Model lookup for the controller's policy gate. See {@see self::findOrFail()}
     * for the DTO read path.
     */
    public function findModelOrFail(int $id): ErrorCode;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ErrorCodeDto;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ErrorCode $errorCode, array $data): ErrorCodeDto;

    public function delete(ErrorCode $errorCode): void;
}

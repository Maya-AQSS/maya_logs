<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\ErrorCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ErrorCodeRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function searchAndFilter(
        ?string $search,
        ?int $filterApp,
        ?string $sortBy = null,
        ?string $sortDir = null,
        int $perPage = 15
    ): LengthAwarePaginator;

    public function findOrFail(int $id): ErrorCode;

    public function create(array $data): ErrorCode;

    public function update(int $id, array $data): ErrorCode;

    public function delete(int $id): void;
}

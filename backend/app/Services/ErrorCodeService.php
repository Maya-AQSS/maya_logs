<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\ErrorCodeDto;
use App\Models\ErrorCode;
use App\Repositories\Contracts\ErrorCodeRepositoryInterface;
use App\Services\Contracts\ErrorCodeServiceInterface;
use Illuminate\Support\Facades\DB;
use Maya\Http\Pagination\PaginatedDto;

class ErrorCodeService implements ErrorCodeServiceInterface
{
    public function __construct(
        private ErrorCodeRepositoryInterface $errorCodeRepository
    ) {}

    public function paginate(int $perPage = 15): PaginatedDto
    {
        return PaginatedDto::fromPaginator(
            $this->errorCodeRepository->paginate($perPage),
            static fn (ErrorCode $m) => ErrorCodeDto::fromModel($m),
        );
    }

    public function searchAndFilter(
        ?string $search,
        ?int $filterApp,
        int $perPage = 15
    ): PaginatedDto {
        return PaginatedDto::fromPaginator(
            $this->errorCodeRepository->searchAndFilter($search, $filterApp, $perPage),
            static fn (ErrorCode $m) => ErrorCodeDto::fromModel($m),
        );
    }

    public function findOrFail(int $id): ErrorCodeDto
    {
        return ErrorCodeDto::fromModel($this->findModelOrFail($id));
    }

    public function findModelOrFail(int $id): ErrorCode
    {
        return $this->errorCodeRepository->findOrFail($id);
    }

    public function create(array $data): ErrorCodeDto
    {
        $errorCode = $this->errorCodeRepository->create($data);
        $errorCode->loadMissing('application');

        return ErrorCodeDto::fromModel($errorCode);
    }

    public function update(ErrorCode $errorCode, array $data): ErrorCodeDto
    {
        $updated = $this->errorCodeRepository->update($errorCode, $data);
        $updated->loadMissing('application');
        $updated->loadCount('comments');

        return ErrorCodeDto::fromModel($updated);
    }

    public function delete(ErrorCode $errorCode): void
    {
        DB::transaction(function () use ($errorCode) {
            $this->errorCodeRepository->delete($errorCode);
        });
    }
}

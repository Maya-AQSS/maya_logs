<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\ErrorCodeDto;
use App\Models\ErrorCode;
use App\Repositories\Contracts\ErrorCodeRepositoryInterface;
use App\Services\Contracts\ErrorCodeServiceInterface;
use Maya\Http\Pagination\PaginatedDto;
use Maya\Messaging\Publishers\ResilientLogPublisher;
use Maya\Messaging\Support\MessagingConfig;
use Throwable;

class ErrorCodeService implements ErrorCodeServiceInterface
{
    private const CODE_NOT_FOUND = 'LAR-LOG-010';

    private const CODE_CREATE_FAILED = 'LAR-LOG-011';

    private const CODE_UPDATE_FAILED = 'LAR-LOG-012';

    private const CODE_DELETE_FAILED = 'LAR-LOG-013';

    public function __construct(
        private ErrorCodeRepositoryInterface $errorCodeRepository,
        private ResilientLogPublisher $resilientLogPublisher,
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
        ?string $sortBy = null,
        ?string $sortDir = null,
        int $perPage = 15
    ): PaginatedDto {
        return PaginatedDto::fromPaginator(
            $this->errorCodeRepository->searchAndFilter($search, $filterApp, $sortBy, $sortDir, $perPage),
            static fn (ErrorCode $m) => ErrorCodeDto::fromModel($m),
        );
    }

    public function findOrFail(int $id): ErrorCodeDto
    {
        return ErrorCodeDto::fromModel($this->findModelOrFail($id));
    }

    /**
     * Sin telemetría en listados (evita ruido); solo se publica a maya.logs si falla la carga por id.
     * Público solo para el policy gate del controlador; para el read path usar {@see self::findOrFail()}.
     */
    public function findModelOrFail(int $id): ErrorCode
    {
        try {
            return $this->errorCodeRepository->findOrFail($id);
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                self::CODE_NOT_FOUND,
                ['error_code_id' => $id],
                MessagingConfig::appSlug(),
            );
            throw $e;
        }
    }

    public function create(array $data): ErrorCodeDto
    {
        try {
            $errorCode = $this->errorCodeRepository->create($data);
            $errorCode->loadMissing('application');

            return ErrorCodeDto::fromModel($errorCode);
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                self::CODE_CREATE_FAILED,
                ['payload_keys' => array_keys($data)],
                MessagingConfig::appSlug(),
            );
            throw $e;
        }
    }

    public function update(int $id, array $data): ErrorCodeDto
    {
        try {
            $updated = $this->errorCodeRepository->update($id, $data);
            $updated->loadMissing('application');
            $updated->loadCount('comments');

            return ErrorCodeDto::fromModel($updated);
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                self::CODE_UPDATE_FAILED,
                ['error_code_id' => $id],
                MessagingConfig::appSlug(),
            );
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        try {
            $this->errorCodeRepository->delete($id);
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                self::CODE_DELETE_FAILED,
                ['error_code_id' => $id],
                MessagingConfig::appSlug(),
            );
            throw $e;
        }
    }
}

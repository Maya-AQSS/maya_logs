<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationPluckScope;
use App\Repositories\Contracts\ApplicationRepositoryInterface;
use App\Services\Contracts\ApplicationServiceInterface;
use Illuminate\Support\Collection;
use Maya\Platform\Support\CachesFilterOptions;

class ApplicationService implements ApplicationServiceInterface
{
    use CachesFilterOptions;

    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private ApplicationRepositoryInterface $applicationRepository
    ) {}

    public function pluckForFilter(ApplicationPluckScope $scope): Collection
    {
        // v2: clave versionada. Si la firma del closure cambia (array ↔ Collection)
        // bumpear el sufijo invalida implícitamente caché stale tras deploy/reset.
        return $this->rememberFilterOptions(
            'applications:pluck_for_filter:v2:'.$scope->value,
            self::CACHE_TTL_SECONDS,
            fn (): Collection => $this->applicationRepository->pluckForFilter($scope)
        );
    }
}

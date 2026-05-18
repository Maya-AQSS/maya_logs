<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationPluckScope;
use App\Repositories\Contracts\ApplicationRepositoryInterface;
use App\Services\Contracts\ApplicationServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ApplicationService implements ApplicationServiceInterface
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private ApplicationRepositoryInterface $applicationRepository
    ) {}

    public function pluckForFilter(ApplicationPluckScope $scope): Collection
    {
        $key = 'applications:pluck_for_filter:'.$scope->value;

        return Cache::remember(
            $key,
            self::CACHE_TTL_SECONDS,
            fn (): Collection => $this->applicationRepository->pluckForFilter($scope)
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Enums\ApplicationPluckScope;
use Illuminate\Support\Collection;

interface ApplicationServiceInterface
{
    /**
     * Applications for filter dropdowns (cached per scope).
     *
     * @return Collection<int|string, string> id => name
     */
    public function pluckForFilter(ApplicationPluckScope $scope): Collection;
}

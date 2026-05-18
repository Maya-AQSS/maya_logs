<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\ApplicationPluckScope;
use Illuminate\Support\Collection;

interface ApplicationRepositoryInterface
{
    /**
     * @return Collection<int|string, string> id => name
     */
    public function pluckForFilter(ApplicationPluckScope $scope): Collection;
}

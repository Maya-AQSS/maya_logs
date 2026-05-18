<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\ApplicationPluckScope;
use App\Models\Application;
use App\Repositories\Contracts\ApplicationRepositoryInterface;
use Illuminate\Support\Collection;

class ApplicationRepository implements ApplicationRepositoryInterface
{
    public function pluckForFilter(ApplicationPluckScope $scope): Collection
    {
        $query = Application::query()->orderBy('name');

        match ($scope) {
            ApplicationPluckScope::WithLogs => $query->whereHas('logs'),
            ApplicationPluckScope::WithArchivedLogs => $query->whereHas('archivedLogs'),
            ApplicationPluckScope::All => null,
        };

        return $query->pluck('name', 'id');
    }
}

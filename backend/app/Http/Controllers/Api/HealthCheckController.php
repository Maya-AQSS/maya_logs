<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Maya\Http\Controllers\AbstractHealthCheckController;
use Maya\Http\Health\DatabaseHealthCheck;
use Maya\Http\Health\HealthCheck;

class HealthCheckController extends AbstractHealthCheckController
{
    /**
     * @return array<int, HealthCheck>
     */
    protected function checks(): array
    {
        return [
            new DatabaseHealthCheck,
        ];
    }
}

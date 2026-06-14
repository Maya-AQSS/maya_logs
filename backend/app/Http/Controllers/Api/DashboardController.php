<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Dtos\DashboardSummaryDto;
use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardSummaryResource;
use App\Services\Contracts\LogServiceInterface;
use Illuminate\Http\JsonResponse;
use Maya\Http\Concerns\RespondsWithEnvelope;

class DashboardController extends Controller
{
    use RespondsWithEnvelope;

    public function __construct(private LogServiceInterface $logService) {}

    public function index(): JsonResponse
    {
        $dto = new DashboardSummaryDto(
            severityCards: $this->logService->dashboardSeverityCards(),
            applicationTotals: $this->logService->dashboardApplicationTotals(),
        );

        return $this->okData(new DashboardSummaryResource($dto));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Dtos\DashboardSummaryDto;
use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardSummaryResource;
use App\Services\Contracts\LogServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private LogServiceInterface $logService) {}

    public function index(Request $request): JsonResponse
    {
        $dto = new DashboardSummaryDto(
            severityCards: $this->logService->dashboardSeverityCards(),
            applicationTotals: $this->logService->dashboardApplicationTotals(),
        );

        return response()->json([
            'data' => (new DashboardSummaryResource($dto))->resolve($request),
        ]);
    }
}

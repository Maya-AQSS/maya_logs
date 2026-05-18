<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Dtos\ApplicationRefDto;
use App\Enums\ApplicationPluckScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationRefResource;
use App\Services\Contracts\ApplicationServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function __construct(
        private ApplicationServiceInterface $applicationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $scope = ApplicationPluckScope::tryFrom(
            $request->string('scope')->toString()
        ) ?? ApplicationPluckScope::All;

        $applications = $this->applicationService->pluckForFilter($scope);

        $dtos = $applications
            ->map(fn (string $name, int|string $id): ApplicationRefDto => new ApplicationRefDto(
                id: (int) $id,
                name: $name,
            ))
            ->values()
            ->all();

        return response()->json([
            'data' => ApplicationRefResource::collection($dtos)->resolve($request),
        ]);
    }
}

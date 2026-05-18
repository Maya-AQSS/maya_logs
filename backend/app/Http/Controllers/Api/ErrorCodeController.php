<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreErrorCodeRequest;
use App\Http\Requests\Api\UpdateErrorCodeRequest;
use App\Http\Resources\ErrorCodeResource;
use App\Models\ErrorCode;
use App\Services\Contracts\ErrorCodeServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErrorCodeController extends Controller
{
    public function __construct(
        private ErrorCodeServiceInterface $errorCodeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);

        $page = $this->errorCodeService->searchAndFilter(
            search: $request->string('search')->toString() ?: null,
            filterApp: $request->filled('application_id') ? (int) $request->input('application_id') : null,
            perPage: $perPage > 0 ? $perPage : 15,
        );

        return response()->json([
            ...$page->jsonSerialize(),
            'data' => ErrorCodeResource::collection($page->items)->resolve($request),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $dto = $this->errorCodeService->findOrFail($id);

        return response()->json([
            'data' => (new ErrorCodeResource($dto))->resolve(),
        ]);
    }

    public function store(StoreErrorCodeRequest $request): JsonResponse
    {
        $this->authorize('create', ErrorCode::class);

        $dto = $this->errorCodeService->create($request->validated());

        return response()->json([
            'data' => (new ErrorCodeResource($dto))->resolve($request),
        ], 201);
    }

    public function update(UpdateErrorCodeRequest $request, int $id): JsonResponse
    {
        $errorCode = $this->errorCodeService->findModelOrFail($id);

        $this->authorize('update', $errorCode);

        $dto = $this->errorCodeService->update($errorCode, $request->validated());

        return response()->json([
            'data' => (new ErrorCodeResource($dto))->resolve($request),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $errorCode = $this->errorCodeService->findModelOrFail($id);

        $this->authorize('delete', $errorCode);

        $this->errorCodeService->delete($errorCode);

        return response()->json(null, 204);
    }
}

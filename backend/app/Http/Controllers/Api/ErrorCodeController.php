<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListErrorCodesRequest;
use App\Http\Requests\Api\StoreErrorCodeRequest;
use App\Http\Requests\Api\UpdateErrorCodeRequest;
use App\Http\Resources\ErrorCodeResource;
use App\Models\ErrorCode;
use App\Services\Contracts\ErrorCodeServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maya\Http\Concerns\RespondsWithEnvelope;

class ErrorCodeController extends Controller
{
    use RespondsWithEnvelope;

    public function __construct(
        private ErrorCodeServiceInterface $errorCodeService,
    ) {}

    public function index(ListErrorCodesRequest $request): JsonResponse
    {
        $page = $this->errorCodeService->searchAndFilter(
            search: $request->string('search')->toString() ?: null,
            filterApp: $request->input('application_id') ? (int) $request->input('application_id') : null,
            sortBy: $request->string('sort_by')->toString() ?: null,
            sortDir: $request->string('sort_dir')->toString() ?: null,
            perPage: $request->getPerPage(),
        );

        return $this->paginated($page, ErrorCodeResource::class, $request);
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
        // Load ErrorCode for authorization check
        $errorCode = ErrorCode::query()->findOrFail($id);

        $this->authorize('update', $errorCode);

        $dto = $this->errorCodeService->update($id, $request->validated());

        return response()->json([
            'data' => (new ErrorCodeResource($dto))->resolve($request),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        // Load ErrorCode for authorization check
        $errorCode = ErrorCode::query()->findOrFail($id);

        $this->authorize('delete', $errorCode);

        $this->errorCodeService->delete($id);

        return response()->json(null, 204);
    }
}

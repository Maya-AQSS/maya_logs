<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ResolvesJwtUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListArchivedLogsRequest;
use App\Http\Requests\Api\UpdateArchivedLogRequest;
use App\Http\Resources\ArchivedLogResource;
use App\Services\Contracts\ArchivedLogServiceInterface;
use Illuminate\Http\JsonResponse;

class ArchivedLogController extends Controller
{
    use ResolvesJwtUser;

    public function __construct(
        private ArchivedLogServiceInterface $archivedLogService,
    ) {}

    public function index(ListArchivedLogsRequest $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 15);
        $severity = $request->input('severity');
        if (is_string($severity)) {
            $severity = array_filter(array_map('trim', explode(',', $severity)), fn (string $v): bool => $v !== '');
        }

        $page = $this->archivedLogService->searchAndFilter(
            severities: is_array($severity) && $severity !== [] ? array_values($severity) : null,
            applicationId: $request->filled('application_id') ? (int) $request->input('application_id') : null,
            dateFrom: $request->string('date_from')->toString() ?: null,
            dateTo: $request->string('date_to')->toString() ?: null,
            sortBy: $request->string('sort_by')->toString() ?: null,
            sortDir: $request->string('sort_dir')->toString() ?: 'desc',
            perPage: $perPage > 0 ? $perPage : 15,
        );

        return response()->json([
            ...$page->jsonSerialize(),
            'data' => ArchivedLogResource::collection($page->items)->resolve($request),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $dto = $this->archivedLogService->findOrFail($id);

        return response()->json([
            'data' => (new ArchivedLogResource($dto))->resolve(),
        ]);
    }

    public function update(UpdateArchivedLogRequest $request, int $id): JsonResponse
    {
        $archivedLog = $this->archivedLogService->findModelOrFail($id);

        $this->authorize('update', $archivedLog);

        $this->archivedLogService->updateArchivedFields(
            $archivedLog,
            $request->validated(),
        );

        $dto = $this->archivedLogService->findOrFail($id);

        return response()->json([
            'data' => (new ArchivedLogResource($dto))->resolve($request),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $archivedLog = $this->archivedLogService->findModelOrFail($id);

        $this->authorize('delete', $archivedLog);

        $this->archivedLogService->delete($archivedLog);

        return response()->json(null, 204);
    }
}

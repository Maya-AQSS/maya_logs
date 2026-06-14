<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListArchivedLogsRequest;
use App\Http\Requests\Api\UpdateArchivedLogRequest;
use App\Http\Resources\ArchivedLogResource;
use App\Services\Contracts\ArchivedLogServiceInterface;
use Illuminate\Http\JsonResponse;
use Maya\Http\Concerns\RespondsWithEnvelope;

class ArchivedLogController extends Controller
{
    use RespondsWithEnvelope;

    public function __construct(
        private ArchivedLogServiceInterface $archivedLogService,
    ) {}

    public function index(ListArchivedLogsRequest $request): JsonResponse
    {
        $page = $this->archivedLogService->searchAndFilter(
            severities: $request->getParsedSeverity(),
            applicationId: $request->getApplicationId(),
            dateFrom: $request->getDateFrom(),
            dateTo: $request->getDateTo(),
            sortBy: $request->getSortBy(),
            sortDir: $request->getSortDir(),
            perPage: $request->getPerPage(),
        );

        return $this->paginated($page, ArchivedLogResource::class, $request);
    }

    public function show(int $id): JsonResponse
    {
        $dto = $this->archivedLogService->findOrFail($id);

        return $this->okData(new ArchivedLogResource($dto));
    }

    public function update(UpdateArchivedLogRequest $request, int $id): JsonResponse
    {
        $archivedLog = $this->archivedLogService->findModelOrFail($id);

        $this->authorize('update', $archivedLog);

        $validatedFields = $this->archivedLogService->validateAndFilterFields(
            $request->validated(),
        );

        $this->archivedLogService->updateArchivedFields(
            $archivedLog,
            $validatedFields,
        );

        $dto = $this->archivedLogService->findOrFail($id);

        return $this->okData(new ArchivedLogResource($dto));
    }

    public function destroy(int $id): JsonResponse
    {
        $archivedLog = $this->archivedLogService->findModelOrFail($id);

        $this->authorize('delete', $archivedLog);

        $this->archivedLogService->delete($archivedLog);

        return response()->json(null, 204);
    }
}

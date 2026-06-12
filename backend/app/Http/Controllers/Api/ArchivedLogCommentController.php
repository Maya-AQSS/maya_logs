<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Dtos\CommentDto;
use App\Http\Requests\Api\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\ArchivedLog;
use App\Services\Contracts\ArchivedLogServiceInterface;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\PanelUserService;
use Illuminate\Http\JsonResponse;

class ArchivedLogCommentController extends AbstractCommentController
{
    public function __construct(
        PanelUserService $panelUserService,
        CommentServiceInterface $commentService,
        private readonly ArchivedLogServiceInterface $archivedLogService,
    ) {
        parent::__construct($panelUserService, $commentService);
    }

    protected function commentableType(): string
    {
        return ArchivedLog::class;
    }

    protected function assertParentExists(int $parentId): void
    {
        $this->archivedLogService->findOrFail($parentId);
    }

    /**
     * Wire format histórico de este endpoint: recurso pelado SIN envelope
     * `data` (a diferencia de error-codes). Se preserva tal cual.
     */
    protected function storeResponse(CommentDto $dto, StoreCommentRequest $request): JsonResponse
    {
        return response()->json(new CommentResource($dto), 201);
    }
}

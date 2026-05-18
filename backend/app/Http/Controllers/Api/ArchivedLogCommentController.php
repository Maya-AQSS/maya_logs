<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Services\Contracts\ArchivedLogServiceInterface;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\PanelUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ArchivedLogCommentController extends Controller
{
    public function __construct(
        private readonly PanelUserService $panelUserService,
        private readonly ArchivedLogServiceInterface $archivedLogService,
        private readonly CommentServiceInterface $commentService,
    ) {}

    public function index(int $archivedLogId): AnonymousResourceCollection
    {
        $commentable = $this->archivedLogService->findModelOrFail($archivedLogId);

        return CommentResource::collection(
            $this->commentService->listForCommentable($commentable),
        );
    }

    public function store(StoreCommentRequest $request, int $archivedLogId): JsonResponse
    {
        $commentable = $this->archivedLogService->findModelOrFail($archivedLogId);
        $user = $this->panelUserService->resolveFromJwtRequest($request);

        $dto = $this->commentService->createForCommentable(
            $commentable,
            $user->id,
            $request->validated('content'),
        );

        return response()->json([
            'data' => (new CommentResource($dto))->resolve($request),
        ], 201);
    }
}

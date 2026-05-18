<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\Contracts\ErrorCodeServiceInterface;
use App\Services\PanelUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ErrorCodeCommentController extends Controller
{
    public function __construct(
        private readonly PanelUserService $panelUserService,
        private readonly ErrorCodeServiceInterface $errorCodeService,
        private readonly CommentServiceInterface $commentService,
    ) {}

    public function index(int $errorCodeId): AnonymousResourceCollection
    {
        $commentable = $this->errorCodeService->findModelOrFail($errorCodeId);

        return CommentResource::collection(
            $this->commentService->listForCommentable($commentable),
        );
    }

    public function store(StoreCommentRequest $request, int $errorCodeId): JsonResponse
    {
        $commentable = $this->errorCodeService->findModelOrFail($errorCodeId);
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

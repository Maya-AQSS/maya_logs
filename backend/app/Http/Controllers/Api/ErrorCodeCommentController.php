<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\ErrorCode;
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
        // Verify the ErrorCode exists
        ErrorCode::query()->findOrFail($errorCodeId);

        return CommentResource::collection(
            $this->commentService->listForCommentable('App\Models\ErrorCode', $errorCodeId),
        );
    }

    public function store(StoreCommentRequest $request, int $errorCodeId): JsonResponse
    {
        // Verify the ErrorCode exists
        ErrorCode::query()->findOrFail($errorCodeId);
        $user = $this->panelUserService->resolveFromJwtRequest($request);

        $dto = $this->commentService->createForCommentable(
            'App\Models\ErrorCode',
            $errorCodeId,
            $user->id,
            $request->validated('content'),
        );

        return response()->json([
            'data' => (new CommentResource($dto))->resolve($request),
        ], 201);
    }
}

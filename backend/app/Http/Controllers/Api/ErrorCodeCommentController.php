<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\Contracts\ErrorCodeServiceInterface;
use App\Services\PanelUserService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Maya\Auth\Dtos\JwtProfileDto;

class ErrorCodeCommentController extends Controller
{
    public function __construct(
        private readonly PanelUserService $panelUserService,
        private readonly ErrorCodeServiceInterface $errorCodeService,
        private readonly CommentServiceInterface $commentService,
    ) {}

    public function index(int $errorCodeId): AnonymousResourceCollection
    {
        // Verify the ErrorCode exists (vía service, capa Eloquent solo en repos)
        $this->errorCodeService->findOrFail($errorCodeId);

        return CommentResource::collection(
            $this->commentService->listForCommentable('App\Models\ErrorCode', $errorCodeId),
        );
    }

    public function store(StoreCommentRequest $request, int $errorCodeId): JsonResponse
    {
        // Verify the ErrorCode exists (vía service, capa Eloquent solo en repos)
        $this->errorCodeService->findOrFail($errorCodeId);

        $jwtProfile = $this->extractJwtProfile($request);
        $user = $this->panelUserService->resolveFromJwtProfile($jwtProfile);

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

    private function extractJwtProfile(Request $request): JwtProfileDto
    {
        $jwtProfile = JwtProfileDto::fromRequestAttribute($request);

        if ($jwtProfile === null) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'actor_missing',
                    'message' => __('logs.actor_missing'),
                ],
            ], 403));
        }

        return $jwtProfile;
    }
}

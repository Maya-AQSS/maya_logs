<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCommentActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\PanelUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate as GateFacade;

class CommentController extends Controller
{
    use ResolvesCommentActor;

    public function __construct(
        private readonly PanelUserService $panelUserService,
        private readonly CommentServiceInterface $commentService,
    ) {}

    public function update(UpdateCommentRequest $request, int $id): JsonResponse
    {
        $comment = $this->commentService->findModelOrFail($id);

        $user = $this->resolveActor($request);

        // Gate::forUser requires an Authenticatable; el modelo se obtiene vía
        // service/repo (excepción de capas aceptada, solo para authorize).
        $userModel = $this->panelUserService->resolveAuthenticatable($user->id);
        if ($userModel === null) {
            abort(403, 'Unauthorized');
        }

        GateFacade::forUser($userModel)->authorize('update', $comment);

        $dto = $this->commentService->updateContent($id, $request->validated('content'));

        return response()->json([
            'data' => (new CommentResource($dto))->resolve($request),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $comment = $this->commentService->findModelOrFail($id);

        $user = $this->resolveActor($request);

        // Gate::forUser requires an Authenticatable; el modelo se obtiene vía
        // service/repo (excepción de capas aceptada, solo para authorize).
        $userModel = $this->panelUserService->resolveAuthenticatable($user->id);
        if ($userModel === null) {
            abort(403, 'Unauthorized');
        }

        GateFacade::forUser($userModel)->authorize('delete', $comment);

        $this->commentService->delete($id);

        return response()->json(null, 204);
    }
}

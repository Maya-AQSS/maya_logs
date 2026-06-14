<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Dtos\CommentDto;
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
        // El Service devuelve un DTO (404 si no existe); ningún modelo de dominio
        // cruza la frontera Service→Controller (Opción A — DTO estricto).
        $comment = $this->commentService->findOrFail($id);

        $this->authorizeCommentAction('update', $request, $comment);

        $dto = $this->commentService->updateContent($id, $request->validated('content'));

        return response()->json([
            'data' => (new CommentResource($dto))->resolve($request),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $comment = $this->commentService->findOrFail($id);

        $this->authorizeCommentAction('delete', $request, $comment);

        $this->commentService->delete($id);

        return response()->json(null, 204);
    }

    /**
     * Autoriza una acción sobre el comentario contra {@see CommentPolicy} usando el DTO.
     *
     * {@see GateFacade::forUser()} exige un Authenticatable (el actor), que se resuelve
     * desde el directorio: es el sujeto de la petición, no una entidad de dominio.
     */
    private function authorizeCommentAction(string $ability, Request $request, CommentDto $comment): void
    {
        $user = $this->resolveActor($request);

        $actor = $this->panelUserService->resolveActorAuthenticatable($user->id);
        if ($actor === null) {
            abort(403, __('api.auth.forbidden'));
        }

        GateFacade::forUser($actor)->authorize($ability, $comment);
    }
}

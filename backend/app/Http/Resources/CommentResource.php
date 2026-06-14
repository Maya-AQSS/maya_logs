<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Dtos\CommentDto;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Maya\Auth\Middleware\JwtMiddleware;

/**
 * @property CommentDto $resource
 */
class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CommentDto $dto */
        $dto = $this->resource;

        $authUser = $this->resolveViewerForGates($request);
        // El gate opera sobre el DTO (Opción A — DTO estricto): CommentPolicy está
        // registrada contra CommentDto, no contra el modelo Eloquent.
        $canEdit = $authUser !== null && Gate::forUser($authUser)->check('update', $dto);
        $canDelete = $authUser !== null && Gate::forUser($authUser)->check('delete', $dto);

        $payload = [
            'id' => $dto->id,
            'content' => $dto->content,
            'commentable_type' => $dto->commentableType,
            'commentable_id' => $dto->commentableId,
            'created_at' => $dto->createdAt,
            'updated_at' => $dto->updatedAt,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
        ];

        if ($dto->userLoaded) {
            $payload['user'] = $dto->user !== null
                ? ['id' => $dto->user->id, 'name' => $dto->user->name]
                : null;
        }

        return $payload;
    }

    /**
     * Las rutas API usan middleware JWT stateless: {@see Request::user()} es null en muchos
     * flujos (el guard `api` resuelve User via attribute, pero no siempre se inyecta a
     * tiempo para los Resources). Los flags `can_edit`/`can_delete` deben evaluarse contra
     * el mismo actor que update/delete — fallback al sujeto JWT depositado por el
     * middleware {@see JwtMiddleware} en el attribute `jwt_user`.
     *
     * Cherry-pick conceptual del commit 23af11b de refactor/globalExperts adaptado a la
     * arquitectura DTO de v2.
     */
    private function resolveViewerForGates(Request $request): ?User
    {
        $user = $request->user();
        if ($user instanceof User) {
            return $user;
        }

        /** @var array<string, mixed>|null $jwtUser */
        $jwtUser = $request->attributes->get('jwt_user');
        $subject = is_array($jwtUser) ? ($jwtUser['id'] ?? null) : null;
        if (! is_string($subject) || $subject === '') {
            return null;
        }

        return app(UserRepositoryInterface::class)->findByKey($subject);
    }
}

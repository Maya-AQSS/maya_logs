<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Dtos\CommentDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @property CommentDto $resource
 */
class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CommentDto $dto */
        $dto = $this->resource;

        $authUser = $request->user();
        $canEdit = $authUser !== null && Gate::forUser($authUser)->check('update', $dto->source);
        $canDelete = $authUser !== null && Gate::forUser($authUser)->check('delete', $dto->source);

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
}

<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Comment;
use App\Repositories\Contracts\CommentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CommentRepository implements CommentRepositoryInterface
{
    /**
     * Busca un comentario por su id.
     */
    public function findOrFail(int $id): Comment
    {
        return Comment::query()->findOrFail($id);
    }

    /**
     * Lista los comentarios para un modelo comentable.
     */
    public function listForCommentable(string $commentableType, int $commentableId): Collection
    {
        /** @var Collection<int, Comment> */
        return Comment::query()
            ->where('commentable_type', $commentableType)
            ->where('commentable_id', $commentableId)
            ->with('user')
            ->latest()
            ->get();
    }

    /**
     * Crea un comentario para un modelo comentable.
     */
    public function createForCommentable(
        string $commentableType,
        int $commentableId,
        string $userId,
        string $sanitizedContent,
    ): Comment {
        $comment = Comment::create([
            'commentable_type' => $commentableType,
            'commentable_id' => $commentableId,
            'user_id' => $userId,
            'content' => $sanitizedContent,
        ]);

        // Eager-load user relation
        return $comment->load('user');
    }

    /**
     * Actualiza el contenido de un comentario.
     */
    public function updateContent(int $id, string $sanitizedContent): Comment
    {
        $comment = $this->findOrFail($id);
        $comment->update(['content' => $sanitizedContent]);

        // Return with user relation eager-loaded
        return $comment->refresh()->load('user');
    }

    /**
     * Elimina un comentario.
     */
    public function delete(int $id): void
    {
        $comment = $this->findOrFail($id);
        $comment->delete();
    }
}

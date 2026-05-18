<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Comment;
use App\Repositories\Contracts\CommentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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
    public function listForCommentable(Model $commentable): Collection
    {
        /** @var Collection<int, Comment> */
        return $commentable->comments()
            ->with('user')
            ->latest()
            ->get();
    }

    /**
     * Crea un comentario para un modelo comentable.
     */
    public function createForCommentable(Model $commentable, string $userId, string $sanitizedContent): Comment
    {
        $comment = $commentable->comments()->create([
            'user_id' => $userId,
            'content' => $sanitizedContent,
        ]);

        $comment->loadMissing('user');

        return $comment;
    }

    /**
     * Actualiza el contenido de un comentario.
     */
    public function updateContent(Comment $comment, string $sanitizedContent): Comment
    {
        $comment->update(['content' => $sanitizedContent]);

        return $comment->refresh()->loadMissing('user');
    }

    /**
     * Elimina un comentario.
     */
    public function delete(Comment $comment): void
    {
        $comment->delete();
    }
}

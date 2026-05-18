<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface CommentRepositoryInterface
{
    public function findOrFail(int $id): Comment;

    /**
     * @return Collection<int, Comment>
     */
    public function listForCommentable(Model $commentable): Collection;

    public function createForCommentable(Model $commentable, string $userId, string $sanitizedContent): Comment;

    public function updateContent(Comment $comment, string $sanitizedContent): Comment;

    public function delete(Comment $comment): void;
}

<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Collection;

interface CommentRepositoryInterface
{
    public function findOrFail(int $id): Comment;

    /**
     * @return Collection<int, Comment>
     */
    public function listForCommentable(string $commentableType, int $commentableId): Collection;

    public function createForCommentable(
        string $commentableType,
        int $commentableId,
        string $userId,
        string $sanitizedContent,
    ): Comment;

    public function updateContent(int $id, string $sanitizedContent): Comment;

    public function delete(int $id): void;
}

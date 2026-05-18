<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Dtos\CommentDto;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Model;

interface CommentServiceInterface
{
    public function findOrFail(int $id): CommentDto;

    /**
     * Model lookup needed by the controller's policy gate. See {@see self::findOrFail()}
     * for the DTO read path.
     */
    public function findModelOrFail(int $id): Comment;

    /**
     * @return list<CommentDto>
     */
    public function listForCommentable(Model $commentable): array;

    public function createForCommentable(Model $commentable, string $userId, string $rawContent): CommentDto;

    public function updateContent(Comment $comment, string $rawContent): CommentDto;

    public function delete(Comment $comment): void;
}

<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Dtos\CommentDto;
use App\Models\Comment;

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
    public function listForCommentable(string $commentableType, int $commentableId): array;

    public function createForCommentable(string $commentableType, int $commentableId, string $userId, string $rawContent): CommentDto;

    public function updateContent(int $id, string $rawContent): CommentDto;

    public function delete(int $id): void;
}

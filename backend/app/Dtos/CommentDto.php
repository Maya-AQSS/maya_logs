<?php

declare(strict_types=1);

namespace App\Dtos;

use App\Models\Comment;

final readonly class CommentDto
{
    public function __construct(
        public int $id,
        public string $content,
        public string $commentableType,
        public int $commentableId,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?UserRefDto $user,
        public bool $userLoaded,
        // The Resource needs the raw Comment for Gate::check() (policy gates work on
        // the Eloquent model). We keep a reference to preserve `can_edit` / `can_delete`
        // semantics without re-querying.
        public Comment $source,
    ) {}

    public static function fromModel(Comment $m): self
    {
        $userLoaded = $m->relationLoaded('user');

        return new self(
            id: $m->id,
            content: (string) $m->content,
            commentableType: (string) $m->commentable_type,
            commentableId: (int) $m->commentable_id,
            createdAt: $m->created_at?->toIso8601String(),
            updatedAt: $m->updated_at?->toIso8601String(),
            user: $userLoaded && $m->user !== null
                ? UserRefDto::fromModel($m->user)
                : null,
            userLoaded: $userLoaded,
            source: $m,
        );
    }
}

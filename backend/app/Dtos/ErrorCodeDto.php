<?php

declare(strict_types=1);

namespace App\Dtos;

use App\Models\ErrorCode;

final readonly class ErrorCodeDto
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public ?string $file,
        public ?int $line,
        public ?string $description,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?ApplicationRefDto $application,
        public ?int $commentsCount,
        public bool $applicationLoaded,
    ) {}

    public static function fromModel(ErrorCode $m): self
    {
        $applicationLoaded = $m->relationLoaded('application');

        return new self(
            id: $m->id,
            code: (string) $m->code,
            name: (string) $m->name,
            file: $m->file,
            line: $m->line !== null ? (int) $m->line : null,
            description: $m->description,
            createdAt: $m->created_at?->toIso8601String(),
            updatedAt: $m->updated_at?->toIso8601String(),
            application: $applicationLoaded && $m->application !== null
                ? ApplicationRefDto::fromModel($m->application)
                : null,
            commentsCount: isset($m->comments_count) ? (int) $m->comments_count : null,
            applicationLoaded: $applicationLoaded,
        );
    }
}

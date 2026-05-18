<?php

declare(strict_types=1);

namespace App\Dtos;

use App\Models\ArchivedLog;

final readonly class ArchivedLogDto
{
    /**
     * @param  array<string, mixed>|string|null  $metadata
     */
    public function __construct(
        public int $id,
        public string $severity,
        public string $message,
        public array|string|null $metadata,
        public ?string $metadataFormatted,
        public ?string $description,
        public ?string $urlTutorial,
        public ?string $originalCreatedAt,
        public ?string $archivedAt,
        public ?string $updatedAt,
        public ?string $deletedAt,
        public ?ApplicationRefDto $application,
        public ?UserRefDto $archivedBy,
        public ?ErrorCodeRefDto $errorCode,
        public ?int $commentsCount,
        public bool $applicationLoaded,
        public bool $archivedByLoaded,
        public bool $errorCodeLoaded,
    ) {}

    public static function fromModel(ArchivedLog $m): self
    {
        $applicationLoaded = $m->relationLoaded('application');
        $archivedByLoaded = $m->relationLoaded('archivedBy');
        $errorCodeLoaded = $m->relationLoaded('errorCode');

        return new self(
            id: $m->id,
            severity: (string) $m->severity,
            message: (string) $m->message,
            metadata: $m->metadata,
            metadataFormatted: $m->metadata_formatted,
            description: $m->description,
            urlTutorial: $m->url_tutorial,
            originalCreatedAt: $m->original_created_at?->toIso8601String(),
            archivedAt: $m->archived_at?->toIso8601String(),
            updatedAt: $m->updated_at?->toIso8601String(),
            deletedAt: $m->deleted_at?->toIso8601String(),
            application: $applicationLoaded && $m->application !== null
                ? ApplicationRefDto::fromModel($m->application)
                : null,
            archivedBy: $archivedByLoaded && $m->archivedBy !== null
                ? UserRefDto::fromModel($m->archivedBy)
                : null,
            errorCode: $errorCodeLoaded && $m->errorCode !== null
                ? ErrorCodeRefDto::fromModel($m->errorCode)
                : null,
            commentsCount: isset($m->comments_count) ? (int) $m->comments_count : null,
            applicationLoaded: $applicationLoaded,
            archivedByLoaded: $archivedByLoaded,
            errorCodeLoaded: $errorCodeLoaded,
        );
    }
}

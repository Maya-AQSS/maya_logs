<?php

declare(strict_types=1);

namespace App\Dtos;

use App\Models\Log;

final readonly class LogDto
{
    /**
     * @param  array<string, mixed>|string|null  $metadata
     */
    public function __construct(
        public int $id,
        public string $severity,
        public string $message,
        public array|string|null $metadata,
        public bool $resolved,
        public ?string $file,
        public ?int $line,
        public ?string $createdAt,
        public ?ApplicationRefDto $application,
        public ?ErrorCodeRefDto $errorCode,
        public bool $errorCodeLoaded,
        public bool $applicationLoaded,
    ) {}

    public static function fromModel(Log $m): self
    {
        $applicationLoaded = $m->relationLoaded('application');
        $errorCodeLoaded = $m->relationLoaded('errorCode');

        return new self(
            id: $m->id,
            severity: (string) $m->severity,
            message: (string) $m->message,
            metadata: $m->metadata,
            resolved: (bool) $m->resolved,
            file: $m->file,
            line: $m->line !== null ? (int) $m->line : null,
            createdAt: $m->created_at?->toIso8601String(),
            application: $applicationLoaded && $m->application !== null
                ? ApplicationRefDto::fromModel($m->application)
                : null,
            errorCode: $errorCodeLoaded && $m->errorCode !== null
                ? ErrorCodeRefDto::fromModel($m->errorCode)
                : null,
            applicationLoaded: $applicationLoaded,
            errorCodeLoaded: $errorCodeLoaded,
        );
    }
}

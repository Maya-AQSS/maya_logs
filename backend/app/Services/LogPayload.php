<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Validated, normalized value object for a single inbound log message.
 * Centralizes all array-unpacking and casting logic so LogIngestionService
 * deals with typed data rather than raw AMQP payload arrays.
 */
readonly class LogPayload
{
    public function __construct(
        public string $app,
        public string $severity,
        public string $message,
        public ?string $errorCode,
        public ?string $file,
        public ?int $line,
        public ?string $occurredAt,
        public mixed $metadata,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            app: (string) ($data['app'] ?? ''),
            severity: (string) ($data['severity'] ?? 'other'),
            message: (string) ($data['message'] ?? ''),
            // Normalize empty error_code to null so downstream logic stays null-only.
            errorCode: isset($data['error_code']) && $data['error_code'] !== ''
                ? (string) $data['error_code']
                : null,
            file: isset($data['file']) ? (string) $data['file'] : null,
            line: isset($data['line']) ? (int) $data['line'] : null,
            occurredAt: isset($data['occurred_at']) ? (string) $data['occurred_at'] : null,
            metadata: $data['metadata'] ?? null,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ArchivedLog;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;
use Maya\Messaging\Support\MessagingConfig;

/**
 * Los campos editables de un {@see ArchivedLog} acaban de persistirse.
 */
final class ArchivedLogFieldsWereUpdated implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $previousValue
     * @param  array<string, mixed>  $newValue
     */
    public function __construct(
        public readonly int $archivedLogId,
        public readonly string $archivedByUserId,
        public readonly array $previousValue,
        public readonly array $newValue,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => MessagingConfig::appSlug(),
            'entityType' => 'archived_log',
            'entityId' => (string) $this->archivedLogId,
            'action' => 'update.fields',
            'userId' => $this->archivedByUserId,
            'previousValue' => $this->previousValue !== [] ? $this->previousValue : null,
            'newValue' => $this->newValue !== [] ? $this->newValue : null,
        ];
    }
}

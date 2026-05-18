<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ArchivedLog;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;

/**
 * Un log activo acaba de persistirse como {@see ArchivedLog} (transacción del repositorio ya cerrada).
 */
final class LogWasArchived implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly ArchivedLog $archivedLog,
        public readonly string $archivedByUserId,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => (string) config('messaging.app'),
            'entityType' => 'archived_log',
            'entityId' => (string) $this->archivedLog->id,
            'action' => 'archive',
            'userId' => $this->archivedByUserId,
        ];
    }
}

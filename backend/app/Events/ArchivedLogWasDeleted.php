<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ArchivedLog;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;

/**
 * Un {@see ArchivedLog} acaba de eliminarse (soft delete) en base de datos.
 */
final class ArchivedLogWasDeleted implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly int $archivedLogId,
        public readonly string $archivedByUserId,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => (string) config('messaging.app'),
            'entityType' => 'archived_log',
            'entityId' => (string) $this->archivedLogId,
            'action' => 'delete',
            'userId' => $this->archivedByUserId,
        ];
    }
}

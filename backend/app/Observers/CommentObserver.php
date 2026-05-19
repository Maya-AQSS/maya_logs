<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Model;
use Maya\Messaging\Observers\AbstractAuditableModelObserver;

/**
 * Observer CRUD para {@see Comment}. Publica `created`, `updated`,
 * `deleted` al exchange `maya.audit` vía el patrón canónico
 * `AbstractAuditableModelObserver` shared.
 */
final class CommentObserver extends AbstractAuditableModelObserver
{
    protected function auditEntityType(): string
    {
        return 'comment';
    }

    /** @return list<string> */
    protected function auditTemporalKeys(): array
    {
        return ['created_at', 'updated_at'];
    }

    protected function resolveAuditUserId(Model $model): string
    {
        // Actor preferente: usuario del panel (JWT actor); fallback al
        // `user_id` del propio comentario (autor) si no hay request HTTP.
        $panel = $this->resolvePanelActorUserId(null);

        return $panel ?? (string) $model->getAttribute('user_id');
    }

    public function created(Comment $comment): void
    {
        $this->auditAfterCreate('created', $comment);
    }

    public function updated(Comment $comment): void
    {
        $this->auditAfterUpdate('updated', $comment);
    }

    public function deleted(Comment $comment): void
    {
        $this->auditAfterDelete('deleted', $comment);
    }
}

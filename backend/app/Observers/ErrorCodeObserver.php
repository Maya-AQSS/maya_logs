<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ErrorCode;
use Illuminate\Database\Eloquent\Model;

final class ErrorCodeObserver extends AbstractAuditableModelObserver
{
    protected function auditEntityType(): string
    {
        return 'error_code';
    }

    protected function auditTemporalKeys(): array
    {
        return self::AUDIT_ELOQUENT_TEMPORAL_KEYS;
    }

    protected function resolveAuditUserId(Model $model): string
    {
        return $this->jwtSubjectFromRequest();
    }

    public function created(ErrorCode $errorCode): void
    {
        $this->auditAfterCreate('Creado un código de error', $errorCode);
    }

    public function updated(ErrorCode $errorCode): void
    {
        $this->auditAfterUpdate('Actualizado un código de error', $errorCode);
    }

    public function deleted(ErrorCode $errorCode): void
    {
        $this->auditAfterDelete('Eliminado un código de error', $errorCode);
    }
}

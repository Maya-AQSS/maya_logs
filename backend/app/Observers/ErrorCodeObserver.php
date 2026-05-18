<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ErrorCode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maya\Messaging\Publishers\AuditPublisher;

/**
 * Observer CRUD para {@see ErrorCode}. Publica en `maya.audit` los verbos
 * `created`, `updated`, `deleted` siguiendo el patrón canónico de
 * `events.md` (Caso A — Observer solo). El guard `DB::afterCommit()` evita
 * publicaciones fantasma en transacciones revertidas.
 */
final class ErrorCodeObserver
{
    public function __construct(
        private readonly AuditPublisher $publisher,
    ) {}

    public function created(ErrorCode $errorCode): void
    {
        DB::afterCommit(fn () => $this->publish(
            'created',
            $errorCode,
            null,
            $errorCode->getAttributes(),
        ));
    }

    public function updated(ErrorCode $errorCode): void
    {
        $previous = array_intersect_key($errorCode->getOriginal(), $errorCode->getChanges());

        DB::afterCommit(fn () => $this->publish(
            'updated',
            $errorCode,
            $previous,
            $errorCode->getChanges(),
        ));
    }

    public function deleted(ErrorCode $errorCode): void
    {
        DB::afterCommit(fn () => $this->publish(
            'deleted',
            $errorCode,
            $errorCode->getAttributes(),
            null,
        ));
    }

    /**
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>|null  $new
     */
    private function publish(string $action, ErrorCode $errorCode, ?array $previous, ?array $new): void
    {
        $this->publisher->publish(
            applicationSlug: (string) config('messaging.app'),
            entityType: 'error_code',
            entityId: (string) $errorCode->getKey(),
            action: $action,
            userId: (string) (Auth::id() ?? 'system'),
            previousValue: $previous,
            newValue: $new,
        );
    }
}

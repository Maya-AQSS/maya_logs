<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Maya\Messaging\Contracts\AuditableEvent;

/**
 * Los Events de dominio que implementan {@see AuditableEvent}
 * los recoge el wildcard registrado por `MessagingServiceProvider::boot()` del
 * package `maya-shared-messaging-laravel` — no se registran aquí Listeners locales.
 */
class EventServiceProvider extends ServiceProvider
{
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}

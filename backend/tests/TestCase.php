<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Maya\Messaging\Publishers\AuditPublisher;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Evita publicar en maya.audit (RabbitMQ → maya_audit) cuando los tests
        // crean/actualizan/borran modelos con #[ObservedBy] o disparan AuditableEvent.
        $auditPublisher = Mockery::mock(AuditPublisher::class);
        $auditPublisher->shouldIgnoreMissing();
        $this->app->instance(AuditPublisher::class, $auditPublisher);

        // Vite no compila assets en CI. withoutVite() reemplaza @vite() con
        // un stub para que los Feature tests que renderizan vistas no fallen.
        $this->withoutVite();
        // CSRF está cubierto por los tests de Dusk. Los feature tests verifican
        // lógica de negocio, no tokens de formulario.
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

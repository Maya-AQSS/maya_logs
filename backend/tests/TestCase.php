<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTestingDatabaseIsIsolated();
        // Vite no compila assets en CI. withoutVite() reemplaza @vite() con
        // un stub para que los Feature tests que renderizan vistas no fallen.
        $this->withoutVite();
        // CSRF está cubierto por los tests de Dusk. Los feature tests verifican
        // lógica de negocio, no tokens de formulario.
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    /**
     * Evita que la suite escriba en log_mgmt_db_* cuando el entorno del contenedor
     * pisa phpunit.xml (compose inyecta DB_CONNECTION=pgsql).
     */
    protected function assertTestingDatabaseIsIsolated(): void
    {
        if (! $this->app->environment('testing')) {
            return;
        }

        $connection = (string) config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection === 'sqlite' && $database === ':memory:') {
            return;
        }

        if ($database === 'testing' || (is_string($database) && str_ends_with($database, '_test'))) {
            return;
        }

        $this->fail(sprintf(
            'Los tests no deben usar la BD de desarrollo [%s/%s]. Ejecuta `make test` o revisa phpunit.xml (force="true" en DB_*).',
            $connection,
            (string) $database
        ));
    }
}

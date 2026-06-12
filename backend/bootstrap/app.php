<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response as GateResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    // Evita doble registro: el withEvents() por defecto de configure() descubre Listeners
    // además de App\Providers\EventServiceProvider::$listen (p. ej. Foo y Foo@handle).
    ->withEvents(false)
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Registro común Maya: trustProxies('*') + HandleCors prepended al grupo
        // api (defaults del helper), más los alias y el prepend propios de logs.
        \Maya\Http\Support\CommonMiddleware::register($middleware, [
            'jwt' => \Maya\Auth\Middleware\JwtMiddleware::class,
            'permission' => \Maya\Auth\Middleware\RequirePermissionMiddleware::class,
        ], [
            'apiPrepend' => [
                \App\Http\Middleware\SetLocaleFromAcceptLanguage::class,
            ],
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * El Handler convierte {@see AuthorizationException} en HttpException antes de los
         * renderables; resolvemos la excepción original con {@see Throwable::getPrevious()}.
         * Solo actuamos en rutas `api/*` o cuando el cliente espera JSON.
         *
         * ORDEN DE REGISTRO: este renderable específico DEBE registrarse ANTES que
         * JsonExceptionRenderer (catch-all). Laravel evalúa los renderables en orden
         * de registro y devuelve la primera respuesta no-null, por lo que registrar
         * el específico "después" jamás se ejecutaría: el override se consigue
         * registrándolo primero. Para AuthorizationException se conserva el shape
         * propio de logs {error:{code,message}}; el resto de tipos cae en el
         * renderer JSON uniforme del paquete shared-http ({message,...}).
         */
        $exceptions->renderable(function (\Throwable $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }

            $auth = $e instanceof AuthorizationException
                ? $e
                : $e->getPrevious();

            if (!$auth instanceof AuthorizationException) {
                return null;
            }

            $gateResponse = $auth->response();

            if ($gateResponse instanceof GateResponse && is_string($gateResponse->code())) {
                return response()->json([
                    'error' => [
                        'code' => $gateResponse->code(),
                        'message' => $auth->getMessage(),
                    ],
                ], $auth->status() ?? 403);
            }

            return response()->json([
                'error' => [
                    'code' => 'forbidden',
                    'message' => __('api.auth.forbidden'),
                ],
            ], $auth->status() ?? 403);
        });

        // Renderer JSON uniforme para el resto de tipos en rutas api/* (shared-http).
        // Ver changes.md: el shape de errores no-AuthorizationException pasa del
        // render por defecto de Laravel al envelope uniforme {message[, errors]}.
        \Maya\Http\Exceptions\JsonExceptionRenderer::register($exceptions);
    })->create();

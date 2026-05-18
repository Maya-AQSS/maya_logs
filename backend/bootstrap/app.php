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
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'jwt' => \Maya\Auth\Middleware\JwtMiddleware::class,
            'permission' => \Maya\Auth\Middleware\RequirePermissionMiddleware::class,
        ]);

        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\SetLocaleFromAcceptLanguage::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * El Handler convierte {@see AuthorizationException} en HttpException antes de los
         * renderables; resolvemos la excepción original con {@see Throwable::getPrevious()}.
         * Solo actuamos en rutas `api/*` o cuando el cliente espera JSON.
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
    })->create();

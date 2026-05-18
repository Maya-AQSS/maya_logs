<?php

use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\ArchivedLogCommentController;
use App\Http\Controllers\Api\ArchivedLogController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ErrorCodeCommentController;
use App\Http\Controllers\Api\ErrorCodeController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\LogController;
use Illuminate\Support\Facades\Route;
use Maya\Profile\Routing\MeRoutes;

/*
|--------------------------------------------------------------------------
| API Routes — Maya Logs
|--------------------------------------------------------------------------
| Todas las rutas bajo /api/v1 están protegidas por JwtMiddleware (RS256).
| Los controladores son deliberadamente delgados: delegan a Services.
*/

Route::prefix('v1')->group(function () {

        // ── Health check (sin auth) ────────────────────────────────
        Route::get('/health', [HealthCheckController::class, 'index']);
        Route::get('/health/live', [HealthCheckController::class, 'live']);
        Route::get('/health/ready', [HealthCheckController::class, 'ready']);

        // ── Rutas protegidas por JWT ───────────────────────────────
        Route::middleware('jwt')->group(function () {

                // Perfil del usuario autenticado — endpoints en maya/shared-profile-laravel.
                MeRoutes::register();

                // Dashboard (BFF): cards de severidad + totales por aplicación
                Route::get('/dashboard', [DashboardController::class, 'index']);

                // Aplicaciones (filtros de dropdown)
                Route::get('/applications', [ApplicationController::class, 'index']);

                // Logs
                Route::get('/logs', [LogController::class, 'index']);
                Route::get('/logs/stream', [LogController::class, 'stream']);
                Route::get('/logs/{id}', [LogController::class, 'show'])->whereNumber('id');
                Route::post('/logs/{id}/archive', [LogController::class, 'archive'])->whereNumber('id');
                Route::patch('/logs/{id}/resolve', [LogController::class, 'resolve'])->whereNumber('id');

                // Archived logs
                Route::get('/archived-logs', [ArchivedLogController::class, 'index']);
                Route::get('/archived-logs/{id}', [ArchivedLogController::class, 'show'])->whereNumber('id');
                Route::match(['put', 'patch'], '/archived-logs/{id}', [ArchivedLogController::class, 'update'])->whereNumber('id')->middleware('permission:logs.update');
                Route::delete('/archived-logs/{id}', [ArchivedLogController::class, 'destroy'])->whereNumber('id')->middleware('permission:logs.delete');

                // Comments sobre ArchivedLogs
                Route::get('/archived-logs/{id}/comments', [ArchivedLogCommentController::class, 'index'])->whereNumber('id');
                Route::post('/archived-logs/{id}/comments', [ArchivedLogCommentController::class, 'store'])->whereNumber('id');

                // Error codes
                Route::get('/error-codes', [ErrorCodeController::class, 'index']);
                Route::post('/error-codes', [ErrorCodeController::class, 'store']);
                Route::get('/error-codes/{id}', [ErrorCodeController::class, 'show'])->whereNumber('id');
                Route::match(['put', 'patch'], '/error-codes/{id}', [ErrorCodeController::class, 'update'])->whereNumber('id')->middleware('permission:logs.update');
                Route::delete('/error-codes/{id}', [ErrorCodeController::class, 'destroy'])->whereNumber('id')->middleware('permission:logs.delete');

                // Comments sobre ErrorCodes
                Route::get('/error-codes/{id}/comments', [ErrorCodeCommentController::class, 'index'])->whereNumber('id');
                Route::post('/error-codes/{id}/comments', [ErrorCodeCommentController::class, 'store'])->whereNumber('id');

                // Comments (shallow): update / delete por id
                Route::match(['put', 'patch'], '/comments/{id}', [CommentController::class, 'update'])->whereNumber('id');
                Route::delete('/comments/{id}', [CommentController::class, 'destroy'])->whereNumber('id');
        });

});

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

        // Perfil: solo JWT (sin logs.login). El front necesita /me para saber permisos.
        Route::middleware(['jwt'])->group(function () {
                MeRoutes::register();
        });

        // ── Rutas protegidas por JWT + permiso de acceso a la app ──
        Route::middleware(['jwt', 'permission:logs.login'])->group(function () {

                // Dashboard (BFF): cards de severidad + totales por aplicación
                Route::get('/dashboard', [DashboardController::class, 'index']);

                // Aplicaciones (filtros de dropdown)
                Route::get('/applications', [ApplicationController::class, 'index']);

                // Logs
                Route::get('/logs', [LogController::class, 'index'])->middleware('permission:logs.index');
                Route::get('/logs/stream', [LogController::class, 'stream'])->middleware('permission:logs.index');
                Route::get('/logs/{id}', [LogController::class, 'show'])->whereNumber('id')->middleware('permission:logs.show');
                Route::post('/logs/{id}/archive', [LogController::class, 'archive'])->whereNumber('id')->middleware('permission:archived-logs.create');
                Route::patch('/logs/{id}/resolve', [LogController::class, 'resolve'])->whereNumber('id')->middleware('permission:logs.update');

                // Archived logs
                Route::get('/archived-logs', [ArchivedLogController::class, 'index'])->middleware('permission:archived-logs.index');
                Route::get('/archived-logs/{id}', [ArchivedLogController::class, 'show'])->whereNumber('id')->middleware('permission:archived-logs.show');
                Route::match(['put', 'patch'], '/archived-logs/{id}', [ArchivedLogController::class, 'update'])->whereNumber('id')->middleware('permission:archived-logs.update');
                Route::delete('/archived-logs/{id}', [ArchivedLogController::class, 'destroy'])->whereNumber('id')->middleware('permission:archived-logs.delete');

                // Comments sobre ArchivedLogs
                Route::get('/archived-logs/{id}/comments', [ArchivedLogCommentController::class, 'index'])->whereNumber('id')->middleware('permission:archived-logs.show');
                Route::post('/archived-logs/{id}/comments', [ArchivedLogCommentController::class, 'store'])->whereNumber('id')->middleware('permission:archived-logs.comment.create');

                // Error codes
                Route::get('/error-codes', [ErrorCodeController::class, 'index'])->middleware('permission:error-code.index');
                Route::post('/error-codes', [ErrorCodeController::class, 'store'])->middleware('permission:error-code.create');
                Route::get('/error-codes/{id}', [ErrorCodeController::class, 'show'])->whereNumber('id')->middleware('permission:error-code.show');
                Route::match(['put', 'patch'], '/error-codes/{id}', [ErrorCodeController::class, 'update'])->whereNumber('id')->middleware('permission:error-code.update');
                Route::delete('/error-codes/{id}', [ErrorCodeController::class, 'destroy'])->whereNumber('id')->middleware('permission:error-code.delete');

                // Comments sobre ErrorCodes
                Route::get('/error-codes/{id}/comments', [ErrorCodeCommentController::class, 'index'])->whereNumber('id')->middleware('permission:error-code.show');
                Route::post('/error-codes/{id}/comments', [ErrorCodeCommentController::class, 'store'])->whereNumber('id')->middleware('permission:error-code.comment.create');

                // Comments (shallow): update / delete por id
                Route::match(['put', 'patch'], '/comments/{id}', [CommentController::class, 'update'])->whereNumber('id');
                Route::delete('/comments/{id}', [CommentController::class, 'destroy'])->whereNumber('id');
        });

});

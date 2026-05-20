<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maya\Auth\Middleware\JwtMiddleware;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    $this->withoutMiddleware([JwtMiddleware::class]);

    $this->userId = (string) Str::uuid();
    User::forceCreate([
        'id'         => $this->userId,
        'email'      => 'logs-resource@maya.localhost',
        'name'       => 'Logs Resource Test',
        'first_name' => 'Logs',
        'last_name'  => 'Resource',
        'username'   => 'logsresource',
        'is_active'  => true,
    ]);

    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', ['id' => $userId, 'sub' => $userId]);
    });

    seedLoginPermission();
});

function seedLoginPermission(): void
{
    DB::table('user_resolved_permissions')->insert([
        'user_id'         => test()->userId,
        'permission_slug' => 'logs.login',
    ]);
}

function grantPermission(string $slug): void
{
    DB::table('user_resolved_permissions')->insert([
        'user_id'         => test()->userId,
        'permission_slug' => $slug,
    ]);
}

it('denies logs index without logs.index', function () {
    $response = $this->getJson('/api/v1/logs');

    $response->assertForbidden();
});

it('allows logs index with logs.index', function () {
    grantPermission('logs.index');

    $response = $this->getJson('/api/v1/logs');

    $response->assertOk();
});

it('denies log show without logs.show', function () {
    grantPermission('logs.index');

    $response = $this->getJson('/api/v1/logs/1');

    $response->assertForbidden();
});

it('allows log show with logs.show', function () {
    grantPermission('logs.index');
    grantPermission('logs.show');

    $response = $this->getJson('/api/v1/logs/1');

    expect($response->status())->toBeIn([200, 404]);
});

it('denies resolve without logs.update', function () {
    grantPermission('logs.show');

    $response = $this->patchJson('/api/v1/logs/1/resolve');

    $response->assertForbidden();
});

it('allows resolve with logs.update', function () {
    grantPermission('logs.show');
    grantPermission('logs.update');

    $response = $this->patchJson('/api/v1/logs/1/resolve');

    expect($response->status())->toBeIn([200, 404, 422]);
});

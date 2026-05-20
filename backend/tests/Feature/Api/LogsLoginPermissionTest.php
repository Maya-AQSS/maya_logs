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
        'email'      => 'logs-login@maya.localhost',
        'name'       => 'Logs Login Test',
        'first_name' => 'Logs',
        'last_name'  => 'Test',
        'username'   => 'logstest',
        'is_active'  => true,
    ]);

    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', ['id' => $userId, 'sub' => $userId]);
    });
});

it('denies protected API routes without logs.login', function () {
    $response = $this->getJson('/api/v1/dashboard');

    $response->assertForbidden();
});

it('allows protected API routes when user has logs.login', function () {
    DB::table('user_resolved_permissions')->insert([
        'user_id'          => $this->userId,
        'permission_slug'  => 'logs.login',
    ]);

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk();
});

it('does not require logs.login for health endpoints', function () {
    $response = $this->getJson('/api/v1/health/live');

    $response->assertOk();
});

it('allows GET me without logs.login so the frontend can resolve permissions', function () {
    DB::table('user_resolved_permissions')->insert([
        'user_id'         => $this->userId,
        'permission_slug' => 'logs.index',
    ]);

    $response = $this->getJson('/api/v1/me');

    $response->assertOk();
    expect($response->json('data.permissions'))->toContain('logs.index');
});

it('denies GET me when jwt is missing', function () {
    $this->withMiddleware([JwtMiddleware::class]);

    $this->getJson('/api/v1/me')->assertUnauthorized();
});

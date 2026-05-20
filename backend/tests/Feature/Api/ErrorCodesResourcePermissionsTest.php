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
        'email'      => 'errorcode@maya.localhost',
        'name'       => 'ErrorCode Test',
        'first_name' => 'ErrorCode',
        'last_name'  => 'Test',
        'username'   => 'errorcodetest',
        'is_active'  => true,
    ]);

    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', ['id' => $userId, 'sub' => $userId]);
    });

    DB::table('user_resolved_permissions')->insert([
        'user_id'         => $this->userId,
        'permission_slug' => 'logs.login',
    ]);
});

function grantErrorCodePermission(string $slug): void
{
    DB::table('user_resolved_permissions')->insert([
        'user_id'         => test()->userId,
        'permission_slug' => $slug,
    ]);
}

it('denies error-codes index without error-code.index', function () {
    $this->getJson('/api/v1/error-codes')->assertForbidden();
});

it('allows error-codes index with error-code.index', function () {
    grantErrorCodePermission('error-code.index');

    $this->getJson('/api/v1/error-codes')->assertOk();
});

it('denies error-codes show without error-code.show', function () {
    grantErrorCodePermission('error-code.index');

    $this->getJson('/api/v1/error-codes/1')->assertForbidden();
});

it('denies error-codes create without error-code.create', function () {
    grantErrorCodePermission('error-code.index');

    $this->postJson('/api/v1/error-codes', [
        'application_id' => 1,
        'code'           => 'E001',
        'name'           => 'Test',
    ])->assertForbidden();
});

it('denies error-codes update without error-code.update', function () {
    grantErrorCodePermission('error-code.show');

    $this->patchJson('/api/v1/error-codes/1', [
        'name' => 'Updated',
    ])->assertForbidden();
});

it('denies error-codes delete without error-code.delete', function () {
    grantErrorCodePermission('error-code.show');

    $this->deleteJson('/api/v1/error-codes/1')->assertForbidden();
});

it('denies error-code comment create without error-code.comment.create', function () {
    grantErrorCodePermission('error-code.show');

    $this->postJson('/api/v1/error-codes/1/comments', [
        'content' => '<p>Test comment</p>',
    ])->assertForbidden();
});

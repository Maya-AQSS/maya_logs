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
        'email'      => 'archived@maya.localhost',
        'name'       => 'Archived Test',
        'first_name' => 'Archived',
        'last_name'  => 'Test',
        'username'   => 'archivedtest',
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

function grantArchivedPermission(string $slug): void
{
    DB::table('user_resolved_permissions')->insert([
        'user_id'         => test()->userId,
        'permission_slug' => $slug,
    ]);
}

it('denies archived-logs index without archived-logs.index', function () {
    $this->getJson('/api/v1/archived-logs')->assertForbidden();
});

it('allows archived-logs index with archived-logs.index', function () {
    grantArchivedPermission('archived-logs.index');

    $this->getJson('/api/v1/archived-logs')->assertOk();
});

it('denies archive without archived-logs.create', function () {
    grantArchivedPermission('logs.index');

    $this->postJson('/api/v1/logs/1/archive')->assertForbidden();
});

it('denies archived-logs update without archived-logs.update', function () {
    grantArchivedPermission('archived-logs.show');

    $this->patchJson('/api/v1/archived-logs/1', [
        'description' => 'x',
    ])->assertForbidden();
});

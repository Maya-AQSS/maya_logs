<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maya\Auth\Middleware\JwtMiddleware;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    $this->withoutMiddleware(JwtMiddleware::class);

    $this->userId = (string) Str::uuid();
    User::forceCreate([
        'id'         => $this->userId,
        'email'      => 'app-test@maya.localhost',
        'name'       => 'App Test User',
        'first_name' => 'App',
        'last_name'  => 'User',
        'username'   => 'appuser',
        'is_active'  => true,
    ]);

    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', ['id' => $userId, 'sub' => $userId]);
    });

    seedLogsLoginForTests();
});

function insertApplication(string $name): int
{
    return DB::table('applications')->insertGetId([
        'name'       => $name,
        'slug'       => Str::slug($name) . '-' . Str::random(4),
        'is_active'  => true,
        'created_at' => now(),
    ]);
}

it('returns list of applications with data key', function () {
    insertApplication('App One');
    insertApplication('App Two');

    $response = $this->getJson('/api/v1/applications');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('returns empty data when no applications exist', function () {
    $response = $this->getJson('/api/v1/applications');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

it('accepts scope=with_logs query parameter', function () {
    // App with no logs should not appear
    insertApplication('No Logs App');

    $appWithLogs = insertApplication('Has Logs App');
    DB::table('logs')->insert([
        'application_id' => $appWithLogs,
        'severity'       => 'high',
        'message'        => 'test',
        'file'           => 'foo.php',
        'line'           => 1,
        'metadata'       => json_encode([]),
        'resolved'       => false,
        'created_at'     => now(),
    ]);

    $response = $this->getJson('/api/v1/applications?scope=with_logs');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('Has Logs App');
});

it('falls back to All scope for unknown scope value', function () {
    insertApplication('Test App');

    $response = $this->getJson('/api/v1/applications?scope=bogus_scope');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('returns data in id and name format', function () {
    insertApplication('My Application');

    $response = $this->getJson('/api/v1/applications');

    $response->assertOk();
    $item = $response->json('data.0');
    expect($item)->toHaveKey('id')
        ->and($item)->toHaveKey('name')
        ->and($item['name'])->toBe('My Application');
});

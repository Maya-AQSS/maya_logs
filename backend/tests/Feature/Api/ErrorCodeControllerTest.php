<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Maya\Auth\Middleware\JwtMiddleware;
use Maya\Auth\Middleware\RequirePermissionMiddleware;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    $this->withoutMiddleware([JwtMiddleware::class, RequirePermissionMiddleware::class]);

    $this->userId = (string) Str::uuid();
    $this->user = User::forceCreate([
        'id'         => $this->userId,
        'email'      => 'ec-test@maya.localhost',
        'name'       => 'EC Test User',
        'first_name' => 'EC',
        'last_name'  => 'User',
        'username'   => 'ecuser',
        'is_active'  => true,
    ]);

    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', ['id' => $userId, 'sub' => $userId]);
    });

    // Allow all gate checks so policy doesn't interfere with controller logic tests.
    // Gate::before only fires when Laravel has an authed user; use actingAs to inject one.
    $this->actingAs($this->user);
    Gate::before(function () {
        return true;
    });

    // Create a test application
    $this->appId = DB::table('applications')->insertGetId([
        'name'       => 'EC App',
        'slug'       => 'ec-app-' . Str::random(4),
        'is_active'  => true,
        'created_at' => now(),
    ]);
});

function makeErrorCode(int $appId, array $overrides = []): int
{
    return DB::table('error_codes')->insertGetId(array_merge([
        'application_id' => $appId,
        'code'           => 'ERR_' . Str::random(4),
        'name'           => 'Test Error',
        'file'           => 'app/Test.php',
        'line'           => 10,
        'created_at'     => now(),
        'updated_at'     => now(),
    ], $overrides));
}

// ─── index ────────────────────────────────────────────────────────────────────

it('returns paginated error codes', function () {
    makeErrorCode($this->appId);
    makeErrorCode($this->appId);

    $response = $this->getJson('/api/v1/error-codes');

    $response->assertOk();
    expect($response->json('total'))->toBe(2);
    expect($response->json('data'))->toHaveCount(2);
});

it('returns empty list when no error codes exist', function () {
    $response = $this->getJson('/api/v1/error-codes');

    $response->assertOk();
    expect($response->json('total'))->toBe(0);
});

it('filters error codes by search term', function () {
    makeErrorCode($this->appId, ['code' => 'AUTH_FAIL', 'name' => 'Auth failure']);
    makeErrorCode($this->appId, ['code' => 'DB_CONN', 'name' => 'DB connection']);

    $response = $this->getJson('/api/v1/error-codes?search=AUTH');

    $response->assertOk();
    expect($response->json('total'))->toBe(1)
        ->and($response->json('data.0.code'))->toBe('AUTH_FAIL');
});

it('filters error codes by application_id', function () {
    $appId2 = DB::table('applications')->insertGetId([
        'name'       => 'EC App 2',
        'slug'       => 'ec-app2-' . Str::random(4),
        'is_active'  => true,
        'created_at' => now(),
    ]);
    makeErrorCode($this->appId, ['code' => 'ERR_A']);
    makeErrorCode($appId2, ['code' => 'ERR_B']);

    $response = $this->getJson("/api/v1/error-codes?application_id={$appId2}");

    $response->assertOk();
    expect($response->json('total'))->toBe(1)
        ->and($response->json('data.0.code'))->toBe('ERR_B');
});

it('respects per_page parameter', function () {
    foreach (range(1, 5) as $i) {
        makeErrorCode($this->appId, ['code' => "ERR_{$i}"]);
    }

    $response = $this->getJson('/api/v1/error-codes?per_page=2');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('total'))->toBe(5);
});

// ─── show ─────────────────────────────────────────────────────────────────────

it('returns single error code by id', function () {
    $id = makeErrorCode($this->appId, ['code' => 'ERR_SHOW']);

    $response = $this->getJson("/api/v1/error-codes/{$id}");

    $response->assertOk();
    expect($response->json('data.id'))->toBe($id)
        ->and($response->json('data.code'))->toBe('ERR_SHOW');
});

it('returns 404 for non-existent error code', function () {
    $response = $this->getJson('/api/v1/error-codes/99999');

    $response->assertNotFound();
});

// ─── store ────────────────────────────────────────────────────────────────────

it('creates an error code', function () {
    $payload = [
        'application_id' => $this->appId,
        'code'           => 'NEW_ERR',
        'name'           => 'New Error',
        'file'           => 'app/NewError.php',
        'line'           => 42,
    ];

    $response = $this->postJson('/api/v1/error-codes', $payload);

    $response->assertCreated();
    expect($response->json('data.code'))->toBe('NEW_ERR')
        ->and($response->json('data.name'))->toBe('New Error');

    $this->assertDatabaseHas('error_codes', ['code' => 'NEW_ERR']);
});

it('returns 422 when creating error code with missing required fields', function () {
    $response = $this->postJson('/api/v1/error-codes', []);

    $response->assertUnprocessable();
    expect($response->json('errors'))->toHaveKey('application_id')
        ->and($response->json('errors'))->toHaveKey('code')
        ->and($response->json('errors'))->toHaveKey('name');
});

it('returns 422 when creating error code with non-existent application_id', function () {
    $response = $this->postJson('/api/v1/error-codes', [
        'application_id' => 99999,
        'code'           => 'ERR_X',
        'name'           => 'Error X',
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors'))->toHaveKey('application_id');
});

it('returns 422 when code is not unique within application', function () {
    makeErrorCode($this->appId, ['code' => 'DUP_CODE']);

    $response = $this->postJson('/api/v1/error-codes', [
        'application_id' => $this->appId,
        'code'           => 'DUP_CODE',
        'name'           => 'Duplicate',
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors'))->toHaveKey('code');
});

// ─── update ───────────────────────────────────────────────────────────────────

it('updates an error code', function () {
    $id = makeErrorCode($this->appId, ['code' => 'OLD_CODE', 'name' => 'Old Name']);

    $response = $this->patchJson("/api/v1/error-codes/{$id}", [
        'application_id' => $this->appId,
        'code'           => 'NEW_CODE',
        'name'           => 'New Name',
    ]);

    $response->assertOk();
    expect($response->json('data.code'))->toBe('NEW_CODE')
        ->and($response->json('data.name'))->toBe('New Name');

    $this->assertDatabaseHas('error_codes', ['id' => $id, 'code' => 'NEW_CODE']);
});

it('returns 404 when updating non-existent error code', function () {
    $response = $this->patchJson('/api/v1/error-codes/99999', [
        'application_id' => $this->appId,
        'code'           => 'ERR_X',
        'name'           => 'Error X',
    ]);

    $response->assertNotFound();
});

it('returns 422 when update has invalid data', function () {
    $id = makeErrorCode($this->appId);

    $response = $this->patchJson("/api/v1/error-codes/{$id}", []);

    $response->assertUnprocessable();
});

// ─── destroy ──────────────────────────────────────────────────────────────────

it('deletes an error code', function () {
    $id = makeErrorCode($this->appId, ['code' => 'TO_DELETE']);

    $response = $this->deleteJson("/api/v1/error-codes/{$id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('error_codes', ['id' => $id]);
});

it('returns 404 when deleting non-existent error code', function () {
    $response = $this->deleteJson('/api/v1/error-codes/99999');

    $response->assertNotFound();
});

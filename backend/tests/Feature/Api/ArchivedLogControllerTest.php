<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maya\Auth\Middleware\JwtMiddleware;
use Maya\Auth\Middleware\RequirePermissionMiddleware;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([JwtMiddleware::class, RequirePermissionMiddleware::class]);

    $this->userId = (string) Str::uuid();
    $this->user = User::forceCreate([
        'id'         => $this->userId,
        'email'      => 'arch-test@maya.localhost',
        'name'       => 'Arch User',
        'first_name' => 'Arch',
        'last_name'  => 'User',
        'username'   => 'archuser',
        'is_active'  => true,
    ]);

    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', ['id' => $userId, 'sub' => $userId]);
    });

    // Create test application
    $this->appId = DB::table('applications')->insertGetId([
        'name'       => 'Arch App',
        'slug'       => 'arch-app-' . Str::random(4),
        'is_active'  => true,
        'created_at' => now(),
    ]);
});

function makeArchivedLog(int $appId, string $archivedById, array $overrides = []): int
{
    return DB::table('archived_logs')->insertGetId(array_merge([
        'application_id'      => $appId,
        'archived_by_id'      => $archivedById,
        'severity'            => 'high',
        'message'             => 'Archived log message',
        'metadata'            => json_encode([]),
        'archived_at'         => now(),
        'original_created_at' => now(),
        'updated_at'          => now(),
    ], $overrides));
}

// ─── index ────────────────────────────────────────────────────────────────────

it('returns paginated archived logs', function () {
    makeArchivedLog($this->appId, $this->userId);
    makeArchivedLog($this->appId, $this->userId);

    $response = $this->getJson('/api/v1/archived-logs');

    $response->assertOk();
    expect($response->json('total'))->toBe(2);
    expect($response->json('data'))->toHaveCount(2);
});

it('returns empty list when no archived logs exist', function () {
    $response = $this->getJson('/api/v1/archived-logs');

    $response->assertOk();
    expect($response->json('total'))->toBe(0);
});

it('filters archived logs by severity', function () {
    makeArchivedLog($this->appId, $this->userId, ['severity' => 'critical']);
    makeArchivedLog($this->appId, $this->userId, ['severity' => 'low']);

    $response = $this->getJson('/api/v1/archived-logs?severity=critical');

    $response->assertOk();
    expect($response->json('total'))->toBe(1)
        ->and($response->json('data.0.severity'))->toBe('critical');
});

// ─── show ────────────────────────────────────────────────────────────────────

it('returns single archived log by id', function () {
    $id = makeArchivedLog($this->appId, $this->userId);

    $response = $this->getJson("/api/v1/archived-logs/{$id}");

    $response->assertOk();
    expect($response->json('data.id'))->toBe($id);
});

it('returns 404 for non-existent archived log', function () {
    $response = $this->getJson('/api/v1/archived-logs/99999');

    $response->assertNotFound();
});

// ─── update ──────────────────────────────────────────────────────────────────

it('allows update when jwt subject matches archived_by_id', function () {
    $id = makeArchivedLog($this->appId, $this->userId);

    $response = $this->patchJson("/api/v1/archived-logs/{$id}", [
        'description' => 'Updated description',
    ]);

    $response->assertOk();
    expect($response->json('data.description'))->toBe('Updated description');
});

it('returns 403 when trying to update archived log owned by another user', function () {
    $otherId = (string) Str::uuid();
    $id = makeArchivedLog($this->appId, $otherId); // archived by different user

    $response = $this->patchJson("/api/v1/archived-logs/{$id}", [
        'description' => 'Should fail',
    ]);

    $response->assertForbidden();
});

// ─── destroy ─────────────────────────────────────────────────────────────────

it('allows delete when jwt subject matches archived_by_id', function () {
    $id = makeArchivedLog($this->appId, $this->userId);

    $response = $this->deleteJson("/api/v1/archived-logs/{$id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('archived_logs', ['id' => $id, 'deleted_at' => null]);
});

it('returns 403 when trying to delete archived log owned by another user', function () {
    $otherId = (string) Str::uuid();
    $id = makeArchivedLog($this->appId, $otherId);

    $response = $this->deleteJson("/api/v1/archived-logs/{$id}");

    $response->assertForbidden();
});

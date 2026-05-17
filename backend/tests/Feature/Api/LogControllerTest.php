<?php

declare(strict_types=1);

use App\Models\ArchivedLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maya\Auth\Middleware\JwtMiddleware;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(JwtMiddleware::class);

    $this->userId = (string) Str::uuid();
    $this->user   = User::forceCreate([
        'id'         => $this->userId,
        'email'      => 'test@maya.localhost',
        'name'       => 'Test User',
        'first_name' => 'Test',
        'last_name'  => 'User',
        'username'   => 'testuser',
        'is_active'  => true,
    ]);

    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', [
            'id'  => $userId,
            'sub' => $userId,
        ]);
    });
});

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeApplication(array $overrides = []): int
{
    return DB::table('applications')->insertGetId(array_merge([
        'name'       => 'Test App',
        'slug'       => 'test-app-' . Str::random(6),
        'is_active'  => true,
        'created_at' => now(),
        // applications stub no tiene updated_at (FDW de maya_auth solo expone created_at).
    ], $overrides));
}

function makeLog(array $overrides = []): int
{
    $appId = $overrides['application_id'] ?? makeApplication();

    return DB::table('logs')->insertGetId(array_merge([
        'application_id' => $appId,
        'severity'       => 'high',
        'message'        => 'Something went wrong',
        'file'           => 'app/Http/Controllers/FooController.php',
        'line'           => 42,
        'metadata'       => json_encode([]),
        'resolved'       => false,
        'created_at'     => now(),
    ], $overrides));
}

// ─── index ────────────────────────────────────────────────────────────────────

it('returns a paginated list of logs', function () {
    makeLog();
    makeLog();

    $response = $this->getJson('/api/v1/logs');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('total'))->toBe(2);
});

it('filters logs by severity', function () {
    makeLog(['severity' => 'high']);
    makeLog(['severity' => 'medium']);

    $response = $this->getJson('/api/v1/logs?severity=high');

    $response->assertOk();
    $severities = collect($response->json('data'))->pluck('severity')->unique()->values()->all();
    expect($severities)->toBe(['high']);
});

it('filters logs by multiple severities comma-separated', function () {
    makeLog(['severity' => 'high']);
    makeLog(['severity' => 'medium']);
    makeLog(['severity' => 'low']);

    $response = $this->getJson('/api/v1/logs?severity=high,medium');

    $response->assertOk();
    $severities = collect($response->json('data'))->pluck('severity')->unique()->sort()->values()->all();
    expect($severities)->toContain('high');
    expect($severities)->toContain('medium');
    expect($severities)->not->toContain('low');
});

it('filters logs by application_id', function () {
    $app1 = makeApplication(['name' => 'App One', 'slug' => 'app-one']);
    $app2 = makeApplication(['name' => 'App Two', 'slug' => 'app-two']);
    makeLog(['application_id' => $app1]);
    makeLog(['application_id' => $app2]);

    $response = $this->getJson("/api/v1/logs?application_id={$app1}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('searches logs by message text', function () {
    makeLog(['message' => 'unique-error-abc123']);
    makeLog(['message' => 'some other error']);

    $response = $this->getJson('/api/v1/logs?search=unique-error-abc123');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.message'))->toBe('unique-error-abc123');
});

it('filters resolved logs', function () {
    makeLog(['resolved' => false]);
    makeLog(['resolved' => true]);

    // ListLogsRequest acepta 'resolved' ∈ {only, unresolved} (no 0/1).
    $response = $this->getJson('/api/v1/logs?resolved=only');

    $response->assertOk();
    $resolvedValues = collect($response->json('data'))->pluck('resolved')->all();
    foreach ($resolvedValues as $resolved) {
        expect($resolved)->toBeTrue();
    }
});

it('respects per_page parameter', function () {
    foreach (range(1, 5) as $_) {
        makeLog();
    }

    $response = $this->getJson('/api/v1/logs?per_page=2');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('total'))->toBe(5);
});

it('returns empty data when no logs exist', function () {
    $response = $this->getJson('/api/v1/logs');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
    expect($response->json('total'))->toBe(0);
});

// ─── show ─────────────────────────────────────────────────────────────────────

it('returns a single log by id', function () {
    $logId = makeLog(['message' => 'Specific log entry', 'severity' => 'critical']);

    $response = $this->getJson("/api/v1/logs/{$logId}");

    $response->assertOk();
    expect($response->json('data.id'))->toBe($logId);
    expect($response->json('data.message'))->toBe('Specific log entry');
    expect($response->json('data.severity'))->toBe('critical');
});

it('returns 404 for non-existent log', function () {
    $response = $this->getJson('/api/v1/logs/99999');

    $response->assertNotFound();
});

// Los tests de archivado se eliminan: el agente original asumió un schema con
// columnas `log_id`/`file`/`line` en `archived_logs` que no existen en producción.
// Los endpoints de archivado se cubrirán en una sesión posterior con conocimiento
// correcto del flujo: archived_logs es una tabla independiente (no hay FK a logs).

// ─── resolve ──────────────────────────────────────────────────────────────────

it('marks a log as resolved', function () {
    $logId = makeLog(['resolved' => false]);

    $response = $this->patchJson("/api/v1/logs/{$logId}/resolve");

    $response->assertOk();
    expect($response->json('data.id'))->toBe($logId);
    expect($response->json('data.resolved'))->toBeTrue();

    $log = DB::table('logs')->find($logId);
    expect((bool) $log->resolved)->toBeTrue();
});

it('returns 404 when resolving a non-existent log', function () {
    $response = $this->patchJson('/api/v1/logs/99999/resolve');

    $response->assertNotFound();
});

// ─── stream ───────────────────────────────────────────────────────────────────

it('returns SSE stream with correct content-type', function () {
    $response = $this->get('/api/v1/logs/stream');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');
});

it('stream response contains event and data lines', function () {
    makeLog(['severity' => 'high']);

    $response = $this->get('/api/v1/logs/stream');

    $response->assertOk();
    $content = $response->streamedContent();
    expect($content)->toContain('event: logs');
    expect($content)->toContain('data: ');
});

<?php

declare(strict_types=1);

use App\Repositories\Contracts\LogIngestionRepositoryInterface;
use App\Services\LogIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maya\Messaging\Exceptions\UnrecoverableIngestionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function makeIngestionService(array $slugToId = [], int $batchSize = 1): LogIngestionService
{
    // batchSize: 1 → every ingest() flushes immediately, keeping assertions simple.
    $service = new LogIngestionService(
        repository: app(LogIngestionRepositoryInterface::class),
        batchSize: $batchSize,
    );
    $service->setApplicationMap($slugToId);

    return $service;
}

// ingest() lanza UnrecoverableIngestionException para señalar "drop" — el
// ConsumeQueueCommand la captura (ACK/drop, sin requeue) y registra el warning.
it('drops payload with null app', function () {
    expect(fn () => makeIngestionService(['known' => 1])
        ->ingest(['app' => null, 'severity' => 'low', 'message' => 'test']))
        ->toThrow(UnrecoverableIngestionException::class);

    $this->assertDatabaseCount('logs', 0);
});

it('drops payload with empty app', function () {
    expect(fn () => makeIngestionService(['known' => 1])
        ->ingest(['app' => '', 'severity' => 'low', 'message' => 'test']))
        ->toThrow(UnrecoverableIngestionException::class);

    $this->assertDatabaseCount('logs', 0);
});

it('drops payload with unknown app', function () {
    expect(fn () => makeIngestionService(['known' => 1])
        ->ingest(['app' => 'ghost-app', 'severity' => 'low', 'message' => 'test']))
        ->toThrow(UnrecoverableIngestionException::class);

    $this->assertDatabaseCount('logs', 0);
});

it('persists valid payload', function () {
    makeIngestionService(['my-app' => 7])
        ->ingest([
            'app'      => 'my-app',
            'severity' => 'critical',
            'message'  => 'Something broke',
        ]);

    $this->assertDatabaseHas('logs', [
        'application_id' => 7,
        'severity'       => 'critical',
        'message'        => 'Something broke',
        'resolved'       => 0,
        'error_code_id'  => null,
    ]);
});

it('persists occurred_at when valid', function () {
    $timestamp = '2026-04-15T10:30:00Z';

    makeIngestionService(['my-app' => 1])
        ->ingest([
            'app'         => 'my-app',
            'severity'    => 'low',
            'message'     => 'test',
            'occurred_at' => $timestamp,
        ]);

    $log = DB::table('logs')->first();
    $this->assertNotNull($log->created_at);
    $storedTs = Carbon::parse($log->created_at);
    $this->assertTrue(Carbon::parse($timestamp)->equalTo($storedTs));
});

it('auto-creates error code with code as name', function () {
    makeIngestionService(['my-app' => 3])
        ->ingest([
            'app'        => 'my-app',
            'severity'   => 'high',
            'message'    => 'Error',
            'error_code' => 'EC001',
            'file'       => 'src/Foo.php',
            'line'       => 42,
        ]);

    $this->assertDatabaseHas('error_codes', [
        'code'           => 'EC001',
        'application_id' => 3,
        'name'           => 'EC001',
        'file'           => 'src/Foo.php',
        'line'           => 42,
    ]);

    $ecId = DB::table('error_codes')->value('id');
    $this->assertDatabaseHas('logs', ['error_code_id' => $ecId]);
});

it('does not throw on duplicate error code', function () {
    $service = makeIngestionService(['my-app' => 5]);
    $payload = ['app' => 'my-app', 'severity' => 'low', 'message' => 'err', 'error_code' => 'DUP001'];

    $service->ingest($payload);
    $service->ingest($payload);

    $this->assertDatabaseCount('error_codes', 1);
    $this->assertDatabaseCount('logs', 2);
});

it('sets error_code_id to null when no code provided', function () {
    makeIngestionService(['my-app' => 2])
        ->ingest(['app' => 'my-app', 'severity' => 'low', 'message' => 'no code']);

    $this->assertDatabaseCount('error_codes', 0);
    $this->assertDatabaseHas('logs', ['error_code_id' => null]);
});

it('falls back to now without crashing on malformed timestamp', function () {
    Log::shouldReceive('warning')->once()->withArgs(fn (string $m, array $ctx = []) => isset($ctx['value']) && $ctx['value'] === 'not-a-date');

    makeIngestionService(['my-app' => 1])
        ->ingest([
            'app'         => 'my-app',
            'severity'    => 'low',
            'message'     => 'test',
            'occurred_at' => 'not-a-date',
        ]);

    $this->assertDatabaseCount('logs', 1);
});

it('stores metadata array as json', function () {
    makeIngestionService(['my-app' => 1])
        ->ingest([
            'app'      => 'my-app',
            'severity' => 'low',
            'message'  => 'test',
            'metadata' => ['key' => 'value', 'num' => 42],
        ]);

    $stored = DB::table('logs')->value('metadata');
    $this->assertJson($stored);
    $this->assertSame(['key' => 'value', 'num' => 42], json_decode($stored, true));
});

it('stores null metadata as null', function () {
    makeIngestionService(['my-app' => 1])
        ->ingest(['app' => 'my-app', 'severity' => 'low', 'message' => 'test']);

    $this->assertNull(DB::table('logs')->value('metadata'));
});

it('defaults severity to other when missing', function () {
    makeIngestionService(['my-app' => 1])
        ->ingest(['app' => 'my-app', 'message' => 'no severity']);

    $this->assertDatabaseHas('logs', ['severity' => 'other']);
});

it('drops payload when app has zero id', function () {
    expect(fn () => makeIngestionService(['zero-app' => 0])
        ->ingest(['app' => 'zero-app', 'severity' => 'low', 'message' => 'test']))
        ->toThrow(UnrecoverableIngestionException::class);

    $this->assertDatabaseCount('logs', 0);
});

it('drops payload when app has negative id', function () {
    expect(fn () => makeIngestionService(['neg-app' => -1])
        ->ingest(['app' => 'neg-app', 'severity' => 'low', 'message' => 'test']))
        ->toThrow(UnrecoverableIngestionException::class);

    $this->assertDatabaseCount('logs', 0);
});

it('uses process cache for repeated error codes', function () {
    $service = makeIngestionService(['my-app' => 1]);
    $payload = ['app' => 'my-app', 'severity' => 'low', 'message' => 'x', 'error_code' => 'CACHED'];

    $service->ingest($payload);
    $service->ingest($payload);
    $service->ingest($payload);

    $this->assertDatabaseCount('error_codes', 1);
    $this->assertDatabaseCount('logs', 3);

    $ecId = DB::table('error_codes')->value('id');
    $this->assertSame(3, DB::table('logs')->where('error_code_id', $ecId)->count());
});

it('buffers logs until batch size is reached', function () {
    $service = makeIngestionService(['my-app' => 1], batchSize: 3);
    $payload = ['app' => 'my-app', 'severity' => 'low', 'message' => 'msg'];

    $service->ingest($payload);
    $service->ingest($payload);
    $this->assertDatabaseCount('logs', 0); // Still buffered.

    $service->ingest($payload); // Third message triggers the flush.
    $this->assertDatabaseCount('logs', 3);
});

it('flush drains partial buffer', function () {
    $service = makeIngestionService(['my-app' => 1], batchSize: 10);

    $service->ingest(['app' => 'my-app', 'severity' => 'low', 'message' => 'pending']);
    $this->assertDatabaseCount('logs', 0); // Under threshold — not auto-flushed.

    $service->flush();
    $this->assertDatabaseCount('logs', 1);
});

it('throws InvalidArgumentException for invalid batch size', function () {
    expect(fn () => new LogIngestionService(
        repository: app(LogIngestionRepositoryInterface::class),
        batchSize: 0,
    ))->toThrow(\InvalidArgumentException::class);
});

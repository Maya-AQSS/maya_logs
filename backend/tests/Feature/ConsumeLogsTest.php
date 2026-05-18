<?php

namespace Tests\Feature;

use App\Repositories\Contracts\LogIngestionRepositoryInterface;
use App\Services\LogIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ConsumeLogsTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(array $slugToId = [], int $batchSize = 1): LogIngestionService
    {
        // batchSize: 1 → every ingest() flushes immediately, keeping assertions simple.
        $service = new LogIngestionService(
            repository: app(LogIngestionRepositoryInterface::class),
            batchSize: $batchSize,
        );
        $service->setApplicationMap($slugToId);
        return $service;
    }

    public function test_payload_with_null_app_is_dropped(): void
    {
        $this->makeService(['known' => 1])
            ->ingest(['app' => null, 'severity' => 'low', 'message' => 'test']);

        $this->assertDatabaseCount('logs', 0);
    }

    public function test_payload_with_empty_app_is_dropped(): void
    {
        $this->makeService(['known' => 1])
            ->ingest(['app' => '', 'severity' => 'low', 'message' => 'test']);

        $this->assertDatabaseCount('logs', 0);
    }

    public function test_payload_with_unknown_app_is_dropped_and_logged(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg, array $ctx = []) => isset($ctx['slug']) && $ctx['slug'] === 'ghost-app');

        $this->makeService(['known' => 1])
            ->ingest(['app' => 'ghost-app', 'severity' => 'low', 'message' => 'test']);

        $this->assertDatabaseCount('logs', 0);
    }

    public function test_valid_payload_is_persisted(): void
    {
        $this->makeService(['my-app' => 7])
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
    }

    public function test_occurred_at_is_persisted_when_valid(): void
    {
        $timestamp = '2026-04-15T10:30:00Z';

        $this->makeService(['my-app' => 1])
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
    }

    public function test_error_code_is_auto_created_with_code_as_name(): void
    {
        $this->makeService(['my-app' => 3])
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
    }

    public function test_duplicate_error_code_does_not_throw(): void
    {
        $service = $this->makeService(['my-app' => 5]);
        $payload = ['app' => 'my-app', 'severity' => 'low', 'message' => 'err', 'error_code' => 'DUP001'];

        $service->ingest($payload);
        $service->ingest($payload);

        $this->assertDatabaseCount('error_codes', 1);
        $this->assertDatabaseCount('logs', 2);
    }

    public function test_error_code_id_is_null_when_no_code_provided(): void
    {
        $this->makeService(['my-app' => 2])
            ->ingest(['app' => 'my-app', 'severity' => 'low', 'message' => 'no code']);

        $this->assertDatabaseCount('error_codes', 0);
        $this->assertDatabaseHas('logs', ['error_code_id' => null]);
    }

    public function test_malformed_timestamp_falls_back_to_now_without_crashing(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(fn (string $m, array $ctx = []) => isset($ctx['value']) && $ctx['value'] === 'not-a-date');

        $this->makeService(['my-app' => 1])
            ->ingest([
                'app'         => 'my-app',
                'severity'    => 'low',
                'message'     => 'test',
                'occurred_at' => 'not-a-date',
            ]);

        $this->assertDatabaseCount('logs', 1);
    }

    public function test_metadata_array_is_stored_as_json(): void
    {
        $this->makeService(['my-app' => 1])
            ->ingest([
                'app'      => 'my-app',
                'severity' => 'low',
                'message'  => 'test',
                'metadata' => ['key' => 'value', 'num' => 42],
            ]);

        $stored = DB::table('logs')->value('metadata');
        $this->assertJson($stored);
        $this->assertSame(['key' => 'value', 'num' => 42], json_decode($stored, true));
    }

    public function test_null_metadata_is_stored_as_null(): void
    {
        $this->makeService(['my-app' => 1])
            ->ingest(['app' => 'my-app', 'severity' => 'low', 'message' => 'test']);

        $this->assertNull(DB::table('logs')->value('metadata'));
    }

    public function test_severity_defaults_to_other_when_missing(): void
    {
        $this->makeService(['my-app' => 1])
            ->ingest(['app' => 'my-app', 'message' => 'no severity']);

        $this->assertDatabaseHas('logs', ['severity' => 'other']);
    }

    public function test_app_with_zero_id_is_dropped(): void
    {
        $this->makeService(['zero-app' => 0])
            ->ingest(['app' => 'zero-app', 'severity' => 'low', 'message' => 'test']);

        $this->assertDatabaseCount('logs', 0);
    }

    public function test_app_with_negative_id_is_dropped(): void
    {
        $this->makeService(['neg-app' => -1])
            ->ingest(['app' => 'neg-app', 'severity' => 'low', 'message' => 'test']);

        $this->assertDatabaseCount('logs', 0);
    }

    public function test_repeated_error_code_uses_process_cache(): void
    {
        $service = $this->makeService(['my-app' => 1]);
        $payload = ['app' => 'my-app', 'severity' => 'low', 'message' => 'x', 'error_code' => 'CACHED'];

        $service->ingest($payload);
        $service->ingest($payload);
        $service->ingest($payload);

        $this->assertDatabaseCount('error_codes', 1);
        $this->assertDatabaseCount('logs', 3);

        $ecId = DB::table('error_codes')->value('id');
        $this->assertSame(3, DB::table('logs')->where('error_code_id', $ecId)->count());
    }

    public function test_logs_are_buffered_until_batch_size_is_reached(): void
    {
        $service = $this->makeService(['my-app' => 1], batchSize: 3);
        $payload = ['app' => 'my-app', 'severity' => 'low', 'message' => 'msg'];

        $service->ingest($payload);
        $service->ingest($payload);
        $this->assertDatabaseCount('logs', 0); // Still buffered.

        $service->ingest($payload); // Third message triggers the flush.
        $this->assertDatabaseCount('logs', 3);
    }

    public function test_flush_drains_partial_buffer(): void
    {
        $service = $this->makeService(['my-app' => 1], batchSize: 10);

        $service->ingest(['app' => 'my-app', 'severity' => 'low', 'message' => 'pending']);
        $this->assertDatabaseCount('logs', 0); // Under threshold — not auto-flushed.

        $service->flush();
        $this->assertDatabaseCount('logs', 1);
    }

    public function test_invalid_batch_size_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LogIngestionService(
            repository: app(LogIngestionRepositoryInterface::class),
            batchSize: 0,
        );
    }
}

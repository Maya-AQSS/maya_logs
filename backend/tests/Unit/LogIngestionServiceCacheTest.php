<?php

declare(strict_types=1);

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

use App\Repositories\Contracts\LogIngestionRepositoryInterface;
use App\Services\LogIngestionService;
use Illuminate\Support\Facades\DB;

/**
 * Tests the in-process error_code cache, including the full-reset path
 * triggered when MAX_ERROR_CODE_CACHE entries are exceeded.
 */

it('resets error code cache and continues working after overflow', function () {
    // Subclass to lower MAX_ERROR_CODE_CACHE so the test doesn't need 10k inserts.
    $service = new class (
        repository: app(LogIngestionRepositoryInterface::class),
        batchSize: 1,
    ) extends LogIngestionService {
        protected const MAX_ERROR_CODE_CACHE = 3;
    };
    $service->setApplicationMap(['app' => 1]);

    // Fill cache to exactly MAX (3 distinct codes).
    foreach (['A', 'B', 'C'] as $code) {
        $service->ingest(['app' => 'app', 'severity' => 'low', 'message' => 'x', 'error_code' => $code]);
    }

    // 4th distinct code triggers cache reset; service must still resolve and persist the log.
    $service->ingest(['app' => 'app', 'severity' => 'low', 'message' => 'x', 'error_code' => 'D']);

    $this->assertDatabaseCount('error_codes', 4);
    $this->assertDatabaseCount('logs', 4);

    // After reset, previously-cached codes are re-resolved from DB without errors.
    $service->ingest(['app' => 'app', 'severity' => 'low', 'message' => 'x', 'error_code' => 'A']);
    $this->assertDatabaseCount('error_codes', 4); // No duplicate created.
    $this->assertDatabaseCount('logs', 5);
});

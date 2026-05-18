<?php

declare(strict_types=1);

use App\Models\ArchivedLog;
use App\Models\User;
use App\Repositories\Eloquent\ArchivedLogRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repo = new ArchivedLogRepository();

    $this->user = User::factory()->create();

    $this->appId = DB::table('applications')->insertGetId([
        'name'       => 'Test App',
        'slug'       => 'test-app-' . Str::random(4),
        'is_active'  => true,
        'created_at' => now(),
    ]);
});

function makeArchivedLogRepo(int $appId, string $userId, array $overrides = []): ArchivedLog
{
    return ArchivedLog::create(array_merge([
        'application_id'      => $appId,
        'archived_by_id'      => $userId,
        'severity'            => 'medium',
        'message'             => 'Test error message',
        'metadata'            => [],
        'archived_at'         => now(),
        'original_created_at' => now(),
    ], $overrides));
}

// ─── paginate ─────────────────────────────────────────────────────────────────

it('paginate returns all archived logs', function () {
    makeArchivedLogRepo($this->appId, $this->user->id);
    makeArchivedLogRepo($this->appId, $this->user->id);

    $paginator = $this->repo->paginate(15);

    expect($paginator->total())->toBe(2)
        ->and($paginator->items())->toHaveCount(2);
});

it('paginate returns empty when no archived logs exist', function () {
    $paginator = $this->repo->paginate(15);

    expect($paginator->total())->toBe(0);
});

it('paginate respects per_page parameter', function () {
    foreach (range(1, 5) as $i) {
        makeArchivedLogRepo($this->appId, $this->user->id, ['message' => "Message {$i}"]);
    }

    $paginator = $this->repo->paginate(3);

    expect($paginator->total())->toBe(5)
        ->and($paginator->items())->toHaveCount(3);
});

// ─── searchAndFilter ──────────────────────────────────────────────────────────

it('searchAndFilter returns all when no filters applied', function () {
    makeArchivedLogRepo($this->appId, $this->user->id, ['severity' => 'high']);
    makeArchivedLogRepo($this->appId, $this->user->id, ['severity' => 'low']);

    $paginator = $this->repo->searchAndFilter(null, null, null, null, null, 'desc', 15);

    expect($paginator->total())->toBe(2);
});

it('searchAndFilter filters by severity', function () {
    makeArchivedLogRepo($this->appId, $this->user->id, ['severity' => 'critical']);
    makeArchivedLogRepo($this->appId, $this->user->id, ['severity' => 'low']);
    makeArchivedLogRepo($this->appId, $this->user->id, ['severity' => 'high']);

    $paginator = $this->repo->searchAndFilter(['critical', 'high'], null, null, null, null, 'asc', 15);

    expect($paginator->total())->toBe(2);
});

it('searchAndFilter filters by application_id', function () {
    $appId2 = DB::table('applications')->insertGetId([
        'name'       => 'Other App',
        'slug'       => 'other-app-' . Str::random(4),
        'is_active'  => true,
        'created_at' => now(),
    ]);

    makeArchivedLogRepo($this->appId, $this->user->id);
    makeArchivedLogRepo($appId2, $this->user->id);

    $paginator = $this->repo->searchAndFilter(null, $appId2, null, null, null, 'asc', 15);

    expect($paginator->total())->toBe(1)
        ->and($paginator->items()[0]->application_id)->toBe($appId2);
});

it('searchAndFilter filters by dateFrom', function () {
    makeArchivedLogRepo($this->appId, $this->user->id, [
        'archived_at' => now()->subDays(10),
    ]);
    makeArchivedLogRepo($this->appId, $this->user->id, [
        'archived_at' => now(),
    ]);

    $dateFrom = now()->subDays(1)->toDateTimeString();
    $paginator = $this->repo->searchAndFilter(null, null, $dateFrom, null, null, 'asc', 15);

    expect($paginator->total())->toBe(1);
});

it('searchAndFilter filters by dateTo', function () {
    makeArchivedLogRepo($this->appId, $this->user->id, [
        'archived_at' => now()->subDays(2),
    ]);
    makeArchivedLogRepo($this->appId, $this->user->id, [
        'archived_at' => now(),
    ]);

    $dateTo = now()->subDays(1)->toDateTimeString();
    $paginator = $this->repo->searchAndFilter(null, null, null, $dateTo, null, 'asc', 15);

    expect($paginator->total())->toBe(1);
});

it('searchAndFilter sorts by archived_at ascending', function () {
    $older = makeArchivedLogRepo($this->appId, $this->user->id, ['archived_at' => now()->subHour()]);
    $newer = makeArchivedLogRepo($this->appId, $this->user->id, ['archived_at' => now()]);

    $paginator = $this->repo->searchAndFilter(null, null, null, null, 'archived_at', 'asc', 15);

    $ids = collect($paginator->items())->pluck('id')->all();
    expect($ids[0])->toBe($older->id)
        ->and($ids[1])->toBe($newer->id);
});

it('searchAndFilter sorts by archived_at descending', function () {
    $older = makeArchivedLogRepo($this->appId, $this->user->id, ['archived_at' => now()->subHour()]);
    $newer = makeArchivedLogRepo($this->appId, $this->user->id, ['archived_at' => now()]);

    $paginator = $this->repo->searchAndFilter(null, null, null, null, 'archived_at', 'desc', 15);

    $ids = collect($paginator->items())->pluck('id')->all();
    expect($ids[0])->toBe($newer->id)
        ->and($ids[1])->toBe($older->id);
});

it('searchAndFilter sorts by severity in business order ascending', function () {
    makeArchivedLogRepo($this->appId, $this->user->id, ['severity' => 'low', 'archived_at' => now()->subMinutes(3)]);
    makeArchivedLogRepo($this->appId, $this->user->id, ['severity' => 'critical', 'archived_at' => now()->subMinutes(2)]);
    makeArchivedLogRepo($this->appId, $this->user->id, ['severity' => 'high', 'archived_at' => now()->subMinute()]);

    $paginator = $this->repo->searchAndFilter(null, null, null, null, 'severity', 'asc', 15);

    $severities = collect($paginator->items())->pluck('severity')->all();
    expect($severities[0])->toBe('critical')
        ->and($severities[1])->toBe('high')
        ->and($severities[2])->toBe('low');
});

it('searchAndFilter sorts by severity descending reverses business order', function () {
    makeArchivedLogRepo($this->appId, $this->user->id, ['severity' => 'critical', 'archived_at' => now()->subMinutes(3)]);
    makeArchivedLogRepo($this->appId, $this->user->id, ['severity' => 'low', 'archived_at' => now()->subMinutes(2)]);

    $paginator = $this->repo->searchAndFilter(null, null, null, null, 'severity', 'desc', 15);

    $severities = collect($paginator->items())->pluck('severity')->all();
    expect($severities[0])->toBe('low')
        ->and($severities[1])->toBe('critical');
});

it('searchAndFilter defaults to desc sort for invalid sortBy', function () {
    makeArchivedLogRepo($this->appId, $this->user->id, ['archived_at' => now()->subHour()]);
    makeArchivedLogRepo($this->appId, $this->user->id, ['archived_at' => now()]);

    $paginator = $this->repo->searchAndFilter(null, null, null, null, 'invalid_column', 'asc', 15);

    // default is desc by archived_at — newest first
    expect($paginator->total())->toBe(2);
    $first = $paginator->items()[0];
    $second = $paginator->items()[1];
    expect($first->archived_at->greaterThanOrEqualTo($second->archived_at))->toBeTrue();
});

it('searchAndFilter coerces invalid sortDir to asc', function () {
    makeArchivedLogRepo($this->appId, $this->user->id, ['archived_at' => now()->subHour()]);
    makeArchivedLogRepo($this->appId, $this->user->id, ['archived_at' => now()]);

    // Should not throw, just coerce to 'asc'
    $paginator = $this->repo->searchAndFilter(null, null, null, null, 'archived_at', 'INVALID', 15);

    expect($paginator->total())->toBe(2);
});

it('searchAndFilter returns empty when severity filter matches nothing', function () {
    makeArchivedLogRepo($this->appId, $this->user->id, ['severity' => 'medium']);

    $paginator = $this->repo->searchAndFilter(['critical'], null, null, null, null, 'asc', 15);

    expect($paginator->total())->toBe(0);
});

// ─── findOrFail ───────────────────────────────────────────────────────────────

it('findOrFail returns the archived log with relations', function () {
    $log = makeArchivedLogRepo($this->appId, $this->user->id);

    $found = $this->repo->findOrFail($log->id);

    expect($found->id)->toBe($log->id)
        ->and($found->relationLoaded('application'))->toBeTrue()
        ->and($found->relationLoaded('archivedBy'))->toBeTrue();
});

it('findOrFail throws ModelNotFoundException for missing id', function () {
    expect(fn () => $this->repo->findOrFail(99999))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

// ─── updateArchivedFields ─────────────────────────────────────────────────────

it('updateArchivedFields updates allowed fields', function () {
    $log = makeArchivedLogRepo($this->appId, $this->user->id);

    $this->repo->updateArchivedFields($log, [
        'description' => 'A description',
        'url_tutorial' => 'https://example.com/tutorial',
    ]);

    $this->assertDatabaseHas('archived_logs', [
        'id'          => $log->id,
        'description' => 'A description',
        'url_tutorial' => 'https://example.com/tutorial',
    ]);
});

it('updateArchivedFields ignores non-allowed fields', function () {
    $log = makeArchivedLogRepo($this->appId, $this->user->id, ['message' => 'Original message']);

    $this->repo->updateArchivedFields($log, [
        'message'     => 'Hacked message',  // not in ALLOWED_ARCHIVED_FIELDS
        'description' => 'Allowed change',  // this one IS allowed
    ]);

    $this->assertDatabaseHas('archived_logs', [
        'id'          => $log->id,
        'message'     => 'Original message', // unchanged — message is not allowed
        'description' => 'Allowed change',
    ]);
});

// ─── delete ───────────────────────────────────────────────────────────────────

it('delete soft-deletes the archived log', function () {
    $log = makeArchivedLogRepo($this->appId, $this->user->id);

    $result = $this->repo->delete($log);

    expect($result)->toBeTrue();
    $this->assertSoftDeleted('archived_logs', ['id' => $log->id]);
});

// ─── archiveFromLogId ─────────────────────────────────────────────────────────

it('archiveFromLogId creates a new archived log from an existing log', function () {
    // Bypass the Log model's booted() block by using DB::table
    $logId = DB::table('logs')->insertGetId([
        'application_id' => $this->appId,
        'severity'       => 'high',
        'message'        => 'Archivable log',
        'metadata'       => json_encode(['key' => 'value']),
        'resolved'       => false,
        'created_at'     => now(),
    ]);

    $archived = $this->repo->archiveFromLogId($logId, $this->user->id);

    expect($archived->wasRecentlyCreated)->toBeTrue()
        ->and($archived->message)->toBe('Archivable log')
        ->and($archived->severity)->toBe('high')
        ->and($archived->application_id)->toBe($this->appId);

    $this->assertDatabaseHas('archived_logs', [
        'id'             => $archived->id,
        'message'        => 'Archivable log',
        'archived_by_id' => $this->user->id,
    ]);
});

it('archiveFromLogId returns existing archived log when duplicate already exists', function () {
    // Create a log via DB::table (bypasses model guards)
    $logId = DB::table('logs')->insertGetId([
        'application_id' => $this->appId,
        'severity'       => 'medium',
        'message'        => 'Duplicate message',
        'metadata'       => json_encode([]),
        'resolved'       => false,
        'created_at'     => now(),
    ]);

    // Pre-create an archived log with same fingerprint
    $existing = makeArchivedLogRepo($this->appId, $this->user->id, [
        'severity' => 'medium',
        'message'  => 'Duplicate message',
        'error_code_id' => null,
    ]);

    $returned = $this->repo->archiveFromLogId($logId, $this->user->id);

    expect($returned->id)->toBe($existing->id)
        ->and($returned->wasRecentlyCreated)->toBeFalse();
});

it('archiveFromLogId throws ModelNotFoundException when log does not exist', function () {
    expect(fn () => $this->repo->archiveFromLogId(99999, $this->user->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

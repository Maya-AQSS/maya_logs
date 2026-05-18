<?php

declare(strict_types=1);

use App\Enums\ApplicationPluckScope;
use App\Repositories\Eloquent\ApplicationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repo = new ApplicationRepository();
});

function insertApp(string $name, bool $isActive = true): int
{
    return DB::table('applications')->insertGetId([
        'name'       => $name,
        'slug'       => Str::slug($name) . '-' . Str::random(4),
        'is_active'  => $isActive,
        'created_at' => now(),
    ]);
}

function insertLog(int $appId): void
{
    DB::table('logs')->insert([
        'application_id' => $appId,
        'severity'       => 'high',
        'message'        => 'test',
        'file'           => 'foo.php',
        'line'           => 1,
        'metadata'       => json_encode([]),
        'resolved'       => false,
        'created_at'     => now(),
    ]);
}

function insertArchivedLog(int $appId, string $userId): void
{
    DB::table('archived_logs')->insert([
        'application_id'      => $appId,
        'archived_by_id'      => $userId,
        'severity'            => 'low',
        'message'             => 'archived',
        'metadata'            => json_encode([]),
        'archived_at'         => now(),
        'original_created_at' => now(),
        'updated_at'          => now(),
    ]);
}

it('returns all applications for AllScope ordered by name', function () {
    insertApp('Zebra App');
    insertApp('Alpha App');

    $result = $this->repo->pluckForFilter(ApplicationPluckScope::All);

    expect($result)->toHaveCount(2);
    expect($result->values()->first())->toBe('Alpha App');
    expect($result->values()->last())->toBe('Zebra App');
});

it('returns empty collection when no applications exist', function () {
    $result = $this->repo->pluckForFilter(ApplicationPluckScope::All);

    expect($result)->toBeEmpty();
});

it('returns only applications with logs for WithLogs scope', function () {
    $appWithLogs = insertApp('App With Logs');
    $appWithout = insertApp('App Without');
    insertLog($appWithLogs);

    $result = $this->repo->pluckForFilter(ApplicationPluckScope::WithLogs);

    expect($result)->toHaveCount(1)
        ->and($result->first())->toBe('App With Logs');
});

it('returns only applications with archived logs for WithArchivedLogs scope', function () {
    $userId = (string) Str::uuid();
    $appWithArchived = insertApp('App With Archived');
    $appWithout = insertApp('App Without');
    insertArchivedLog($appWithArchived, $userId);

    $result = $this->repo->pluckForFilter(ApplicationPluckScope::WithArchivedLogs);

    expect($result)->toHaveCount(1)
        ->and($result->first())->toBe('App With Archived');
});

it('returns collection keyed by id', function () {
    $id = insertApp('Key Test');

    $result = $this->repo->pluckForFilter(ApplicationPluckScope::All);

    expect($result->keys()->first())->toBe($id);
});

<?php

declare(strict_types=1);

use App\Models\ErrorCode;
use App\Repositories\Eloquent\ErrorCodeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repo = new ErrorCodeRepository();

    $this->appId = DB::table('applications')->insertGetId([
        'name'       => 'Test App',
        'slug'       => 'test-app-' . Str::random(4),
        'is_active'  => true,
        'created_at' => now(),
    ]);
});

function makeErrorCodeData(int $appId, array $overrides = []): array
{
    return array_merge([
        'application_id' => $appId,
        'code'           => 'ERR_' . Str::random(4),
        'name'           => 'Test Error',
        'file'           => 'app/Test.php',
        'line'           => 10,
    ], $overrides);
}

it('paginates error codes ordered by code', function () {
    ErrorCode::create(makeErrorCodeData($this->appId, ['code' => 'ERR_Z']));
    ErrorCode::create(makeErrorCodeData($this->appId, ['code' => 'ERR_A']));

    $paginator = $this->repo->paginate(10);

    expect($paginator->total())->toBe(2)
        ->and($paginator->items()[0]->code)->toBe('ERR_A');
});

it('returns empty paginator when no error codes exist', function () {
    $paginator = $this->repo->paginate(10);

    expect($paginator->total())->toBe(0);
});

it('searchAndFilter returns all when no search or filter', function () {
    ErrorCode::create(makeErrorCodeData($this->appId, ['code' => 'ERR_001']));
    ErrorCode::create(makeErrorCodeData($this->appId, ['code' => 'ERR_002']));

    $paginator = $this->repo->searchAndFilter(null, null, 15);

    expect($paginator->total())->toBe(2);
});

it('searchAndFilter filters by search term (case insensitive)', function () {
    ErrorCode::create(makeErrorCodeData($this->appId, ['code' => 'AUTH_FAIL', 'name' => 'Auth failure']));
    ErrorCode::create(makeErrorCodeData($this->appId, ['code' => 'DB_CONN', 'name' => 'DB connection']));

    $paginator = $this->repo->searchAndFilter('auth', null, 15);

    expect($paginator->total())->toBe(1)
        ->and($paginator->items()[0]->code)->toBe('AUTH_FAIL');
});

it('searchAndFilter filters by application id', function () {
    $appId2 = DB::table('applications')->insertGetId([
        'name'       => 'Other App',
        'slug'       => 'other-app-' . Str::random(4),
        'is_active'  => true,
        'created_at' => now(),
    ]);
    ErrorCode::create(makeErrorCodeData($this->appId, ['code' => 'ERR_APP1']));
    ErrorCode::create(makeErrorCodeData($appId2, ['code' => 'ERR_APP2']));

    $paginator = $this->repo->searchAndFilter(null, $appId2, 15);

    expect($paginator->total())->toBe(1)
        ->and($paginator->items()[0]->code)->toBe('ERR_APP2');
});

it('searchAndFilter returns empty when search matches nothing', function () {
    ErrorCode::create(makeErrorCodeData($this->appId));

    $paginator = $this->repo->searchAndFilter('XYZNOTFOUND', null, 15);

    expect($paginator->total())->toBe(0);
});

it('findOrFail returns error code with application relation', function () {
    $ec = ErrorCode::create(makeErrorCodeData($this->appId));

    $found = $this->repo->findOrFail($ec->id);

    expect($found->id)->toBe($ec->id)
        ->and($found->relationLoaded('application'))->toBeTrue();
});

it('findOrFail throws ModelNotFoundException for missing id', function () {
    expect(fn () => $this->repo->findOrFail(99999))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('creates an error code', function () {
    $data = makeErrorCodeData($this->appId, ['code' => 'NEW_ERR']);
    $ec = $this->repo->create($data);

    expect($ec->code)->toBe('NEW_ERR')
        ->and($ec->id)->not->toBeNull();
    $this->assertDatabaseHas('error_codes', ['code' => 'NEW_ERR']);
});

it('updates an error code', function () {
    $ec = ErrorCode::create(makeErrorCodeData($this->appId));

    $updated = $this->repo->update($ec, ['name' => 'Updated Name']);

    expect($updated->name)->toBe('Updated Name');
    $this->assertDatabaseHas('error_codes', ['id' => $ec->id, 'name' => 'Updated Name']);
});

it('deletes an error code', function () {
    $ec = ErrorCode::create(makeErrorCodeData($this->appId));

    $this->repo->delete($ec);

    $this->assertDatabaseMissing('error_codes', ['id' => $ec->id]);
});

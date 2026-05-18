<?php

declare(strict_types=1);

use App\Models\ArchivedLog;
use App\Models\User;
use App\Policies\ArchivedLogPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(\Tests\TestCase::class, RefreshDatabase::class);

function makeArchivedLogPolicy(array $jwtUser = []): array
{
    $request = new Request();
    if ($jwtUser !== []) {
        $request->attributes->set('jwt_user', $jwtUser);
    }
    $policy = new ArchivedLogPolicy($request);

    return [$policy, $request];
}

function makeArchivedLogRecord(string $archivedById): ArchivedLog
{
    $log = new ArchivedLog();
    $log->archived_by_id = $archivedById;

    return $log;
}

it('allows update when jwt subject matches archived_by_id', function () {
    $userId = (string) \Illuminate\Support\Str::uuid();
    [$policy] = makeArchivedLogPolicy(['id' => $userId, 'sub' => $userId]);
    $archivedLog = makeArchivedLogRecord($userId);

    $response = $policy->update(null, $archivedLog);

    expect($response->allowed())->toBeTrue();
});

it('denies update when jwt subject does not match archived_by_id', function () {
    $userId = (string) \Illuminate\Support\Str::uuid();
    $otherId = (string) \Illuminate\Support\Str::uuid();
    [$policy] = makeArchivedLogPolicy(['id' => $userId]);
    $archivedLog = makeArchivedLogRecord($otherId);

    $response = $policy->update(null, $archivedLog);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(403);
});

it('denies update when jwt_user is absent', function () {
    $request = new Request();
    $policy = new ArchivedLogPolicy($request);
    $archivedLog = makeArchivedLogRecord('some-id');

    $response = $policy->update(null, $archivedLog);

    expect($response->denied())->toBeTrue()
        ->and($response->message())->not->toBeNull();
});

it('denies update when jwt_user id is empty string', function () {
    [$policy] = makeArchivedLogPolicy(['id' => '']);
    $archivedLog = makeArchivedLogRecord('some-id');

    $response = $policy->update(null, $archivedLog);

    expect($response->denied())->toBeTrue();
});

it('allows delete when jwt subject matches archived_by_id', function () {
    $userId = (string) \Illuminate\Support\Str::uuid();
    [$policy] = makeArchivedLogPolicy(['id' => $userId]);
    $archivedLog = makeArchivedLogRecord($userId);

    $response = $policy->delete(null, $archivedLog);

    expect($response->allowed())->toBeTrue();
});

it('denies delete when jwt subject does not match archived_by_id', function () {
    $userId = (string) \Illuminate\Support\Str::uuid();
    $otherId = (string) \Illuminate\Support\Str::uuid();
    [$policy] = makeArchivedLogPolicy(['id' => $userId]);
    $archivedLog = makeArchivedLogRecord($otherId);

    $response = $policy->delete(null, $archivedLog);

    expect($response->denied())->toBeTrue();
});

it('uses actor_missing code when jwt is absent', function () {
    $request = new Request();
    $policy = new ArchivedLogPolicy($request);
    $archivedLog = makeArchivedLogRecord('some-id');

    $response = $policy->update(null, $archivedLog);

    expect($response->code())->toBe('actor_missing');
});

it('uses archived_log_forbidden code when subject mismatches', function () {
    $userId = (string) \Illuminate\Support\Str::uuid();
    [$policy] = makeArchivedLogPolicy(['id' => $userId]);
    $archivedLog = makeArchivedLogRecord('other-id');

    $response = $policy->update(null, $archivedLog);

    expect($response->code())->toBe('archived_log_forbidden');
});

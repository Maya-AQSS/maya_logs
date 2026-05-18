<?php

declare(strict_types=1);

use App\Models\ErrorCode;
use App\Observers\ErrorCodeObserver;
use Illuminate\Http\Request;
use Maya\Messaging\Publishers\AuditPublisher;

uses(\Tests\TestCase::class);

afterEach(function () {
    Mockery::close();
});

function makeErrorCodeObserver(?AuditPublisher $publisher = null): ErrorCodeObserver
{
    return new ErrorCodeObserver($publisher ?? Mockery::mock(AuditPublisher::class));
}

function makeErrorCodeModel(array $attributes = [], int $id = 42): ErrorCode
{
    $model = new ErrorCode(array_merge([
        'application_id' => 1,
        'code'           => 'ERR_TEST',
        'name'           => 'Test Error',
        'file'           => 'app/Test.php',
        'line'           => 10,
    ], $attributes));
    $model->id = $id;
    $model->syncOriginal();

    return $model;
}

function bindJwtRequest(?array $jwtUser): void
{
    $request = Request::create('/api/v1/error-codes', 'POST');
    if ($jwtUser !== null) {
        $request->attributes->set('jwt_user', $jwtUser);
    }
    app()->instance('request', $request);
}

it('declares afterCommit to avoid phantom audit events on rollback', function () {
    expect(makeErrorCodeObserver()->afterCommit)->toBeTrue();
});

it('created publishes audit with jwt actor and full new value', function () {
    config(['messaging.app' => 'maya_logs']);
    bindJwtRequest(['id' => 'user-uuid-1']);

    $publisher = Mockery::mock(AuditPublisher::class);
    $publisher->shouldReceive('publish')
        ->once()
        ->withArgs(static function (
            string $applicationSlug,
            string $entityType,
            string $entityId,
            string $action,
            string $userId,
            ?string $blockId,
            ?array $previousValue,
            ?array $newValue,
        ): bool {
            return $applicationSlug === 'maya_logs'
                && $entityType === 'error_code'
                && $entityId === '42'
                && $action === 'Creado un código de error'
                && $userId === 'user-uuid-1'
                && $blockId === null
                && $previousValue === null
                && is_array($newValue)
                && ($newValue['code'] ?? null) === 'ERR_TEST';
        });

    $observer = new ErrorCodeObserver($publisher);
    $observer->created(makeErrorCodeModel());
});

it('created uses system actor when jwt_user is absent', function () {
    config(['messaging.app' => 'maya_logs']);
    bindJwtRequest(null);

    $publisher = Mockery::mock(AuditPublisher::class);
    $publisher->shouldReceive('publish')
        ->once()
        ->withArgs(static function (
            string $applicationSlug,
            string $entityType,
            string $entityId,
            string $action,
            string $userId,
        ): bool {
            return $applicationSlug === 'maya_logs'
                && $entityType === 'error_code'
                && $entityId === '42'
                && $action === 'Creado un código de error'
                && $userId === 'system';
        });

    $observer = new ErrorCodeObserver($publisher);
    $observer->created(makeErrorCodeModel());
});

it('updated publishes only changed fields in previous and new value', function () {
    config(['messaging.app' => 'maya_logs']);
    bindJwtRequest(['id' => 'editor-uuid']);

    $publisher = Mockery::mock(AuditPublisher::class);
    $publisher->shouldReceive('publish')
        ->once()
        ->withArgs(static function (
            string $applicationSlug,
            string $entityType,
            string $entityId,
            string $action,
            string $userId,
            ?string $blockId,
            ?array $previousValue,
            ?array $newValue,
        ): bool {
            return $applicationSlug === 'maya_logs'
                && $entityType === 'error_code'
                && $entityId === '7'
                && $action === 'Actualizado un código de error'
                && $userId === 'editor-uuid'
                && $blockId === null
                && is_array($previousValue)
                && ($previousValue['name'] ?? null) === 'Old Name'
                && is_array($newValue)
                && ($newValue['name'] ?? null) === 'New Name'
                && ! array_key_exists('code', $previousValue)
                && ! array_key_exists('code', $newValue);
        });

    $model = makeErrorCodeModel(['name' => 'Old Name'], 7);
    $model->name = 'New Name';
    $model->syncChanges();

    $observer = new ErrorCodeObserver($publisher);
    $observer->updated($model);
});

it('deleted publishes previous attributes and null new value', function () {
    config(['messaging.app' => 'maya_logs']);
    bindJwtRequest(['id' => 'deleter-uuid']);

    $publisher = Mockery::mock(AuditPublisher::class);
    $publisher->shouldReceive('publish')
        ->once()
        ->withArgs(static function (
            string $applicationSlug,
            string $entityType,
            string $entityId,
            string $action,
            string $userId,
            ?string $blockId,
            ?array $previousValue,
            ?array $newValue,
        ): bool {
            return $applicationSlug === 'maya_logs'
                && $entityType === 'error_code'
                && $entityId === '99'
                && $action === 'Eliminado un código de error'
                && $userId === 'deleter-uuid'
                && $blockId === null
                && is_array($previousValue)
                && ($previousValue['code'] ?? null) === 'ERR_DEL'
                && $newValue === null;
        });

    $observer = new ErrorCodeObserver($publisher);
    $observer->deleted(makeErrorCodeModel(['code' => 'ERR_DEL'], 99));
});

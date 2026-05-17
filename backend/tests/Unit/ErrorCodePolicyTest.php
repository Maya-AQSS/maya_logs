<?php

declare(strict_types=1);

use App\Models\ErrorCode;
use App\Models\User;
use App\Policies\ErrorCodePolicy;
use Illuminate\Http\Request;
use Maya\Profile\Dtos\UserProfileDto;
use Maya\Profile\Enums\Locale;
use Maya\Profile\Services\Contracts\UserProfileServiceInterface;

uses(\Tests\TestCase::class);

beforeEach(function () {
    $this->profileService = Mockery::mock(UserProfileServiceInterface::class);
});

afterEach(function () {
    Mockery::close();
});

function makeErrorCodePolicy(array $jwtUser, UserProfileServiceInterface $profileService): ErrorCodePolicy
{
    $request = new Request();
    $request->attributes->set('jwt_user', $jwtUser);

    return new ErrorCodePolicy($request, $profileService);
}

function makeProfileWithPermissions(array $permissions): UserProfileDto
{
    return new UserProfileDto(
        id: 'user-id',
        email: 'test@example.com',
        name: 'Test User',
        locale: Locale::Spanish,
        extra: ['permissions' => $permissions],
    );
}

it('allows create when user has logs.update permission', function () {
    $userId = 'user-uuid';
    $jwtUser = ['id' => $userId];
    $profile = makeProfileWithPermissions(['logs.update']);
    $this->profileService->shouldReceive('getProfile')->andReturn($profile);

    $policy = makeErrorCodePolicy($jwtUser, $this->profileService);

    expect($policy->create(null)->allowed())->toBeTrue();
});

it('denies create when user lacks logs.update permission', function () {
    $userId = 'user-uuid';
    $jwtUser = ['id' => $userId];
    $profile = makeProfileWithPermissions(['logs.read']);
    $this->profileService->shouldReceive('getProfile')->andReturn($profile);

    $policy = makeErrorCodePolicy($jwtUser, $this->profileService);

    expect($policy->create(null)->denied())->toBeTrue()
        ->and($policy->create(null)->status())->toBe(403);
});

it('allows update when user has logs.update permission', function () {
    $userId = 'user-uuid';
    $jwtUser = ['id' => $userId];
    $profile = makeProfileWithPermissions(['logs.update', 'logs.delete']);
    $this->profileService->shouldReceive('getProfile')->andReturn($profile);
    $errorCode = new ErrorCode();

    $policy = makeErrorCodePolicy($jwtUser, $this->profileService);

    expect($policy->update(null, $errorCode)->allowed())->toBeTrue();
});

it('allows delete when user has logs.delete permission', function () {
    $userId = 'user-uuid';
    $jwtUser = ['id' => $userId];
    $profile = makeProfileWithPermissions(['logs.delete']);
    $this->profileService->shouldReceive('getProfile')->andReturn($profile);
    $errorCode = new ErrorCode();

    $policy = makeErrorCodePolicy($jwtUser, $this->profileService);

    expect($policy->delete(null, $errorCode)->allowed())->toBeTrue();
});

it('denies delete when user only has logs.update but not logs.delete', function () {
    $userId = 'user-uuid';
    $jwtUser = ['id' => $userId];
    $profile = makeProfileWithPermissions(['logs.update']);
    $this->profileService->shouldReceive('getProfile')->andReturn($profile);
    $errorCode = new ErrorCode();

    $policy = makeErrorCodePolicy($jwtUser, $this->profileService);

    expect($policy->delete(null, $errorCode)->denied())->toBeTrue();
});

it('denies all when jwt_user id is missing', function () {
    $request = new Request();
    $policy = new ErrorCodePolicy($request, $this->profileService);
    $this->profileService->shouldNotReceive('getProfile');

    expect($policy->create(null)->denied())->toBeTrue();
});

it('denies when permissions array is null in profile', function () {
    $userId = 'user-uuid';
    $jwtUser = ['id' => $userId];
    $profile = new UserProfileDto(
        id: $userId,
        email: 'test@example.com',
        name: 'Test',
        locale: Locale::Spanish,
        extra: [], // no permissions key
    );
    $this->profileService->shouldReceive('getProfile')->andReturn($profile);

    $policy = makeErrorCodePolicy($jwtUser, $this->profileService);

    expect($policy->create(null)->denied())->toBeTrue();
});

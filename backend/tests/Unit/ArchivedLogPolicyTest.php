<?php

declare(strict_types=1);

use App\Models\ArchivedLog;
use App\Policies\ArchivedLogPolicy;
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

function makeArchivedLogPolicy(array $jwtUser): ArchivedLogPolicy
{
    $request = new Request();
    $request->attributes->set('jwt_user', $jwtUser);

    return new ArchivedLogPolicy($request, test()->profileService);
}

function makeArchivedLogProfile(array $permissions): UserProfileDto
{
    return new UserProfileDto(
        id: 'user-1',
        email: 'a@maya.local',
        name: 'Test',
        locale: Locale::Spanish,
        extra: ['permissions' => $permissions],
    );
}

it('allows update when user has archived-logs.update', function () {
    $this->profileService->shouldReceive('getProfile')->once()->andReturn(
        makeArchivedLogProfile(['archived-logs.update']),
    );

    $policy = makeArchivedLogPolicy(['id' => 'user-1']);
    $response = $policy->update(null, new ArchivedLog());

    expect($response->allowed())->toBeTrue();
});

it('denies update when user lacks archived-logs.update', function () {
    $this->profileService->shouldReceive('getProfile')->once()->andReturn(
        makeArchivedLogProfile(['archived-logs.index']),
    );

    $policy = makeArchivedLogPolicy(['id' => 'user-1']);
    $response = $policy->update(null, new ArchivedLog());

    expect($response->denied())->toBeTrue();
});

it('allows delete when user has archived-logs.delete', function () {
    $this->profileService->shouldReceive('getProfile')->once()->andReturn(
        makeArchivedLogProfile(['archived-logs.delete']),
    );

    $policy = makeArchivedLogPolicy(['id' => 'user-1']);
    $response = $policy->delete(null, new ArchivedLog());

    expect($response->allowed())->toBeTrue();
});

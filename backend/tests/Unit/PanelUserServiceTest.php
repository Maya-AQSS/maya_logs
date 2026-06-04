<?php

declare(strict_types=1);

use App\Dtos\JwtProfileDto;
use App\Dtos\UserRefDto;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\PanelUserService;
use Illuminate\Http\Exceptions\HttpResponseException;

uses(\Tests\TestCase::class);

beforeEach(function () {
    $this->repo = Mockery::mock(UserRepositoryInterface::class);
    $this->service = new PanelUserService($this->repo);
});

afterEach(function () {
    Mockery::close();
});

it('resolves user from jwt profile DTO', function () {
    $userDto = new UserRefDto('user-uuid-123', 'Test User');
    $jwtProfile = new JwtProfileDto('user-uuid-123');

    $this->repo->shouldReceive('findByKey')
        ->with('user-uuid-123')
        ->once()
        ->andReturn($userDto);

    $result = $this->service->resolveFromJwtProfile($jwtProfile);

    expect($result)->toBe($userDto);
    expect($result->id)->toBe('user-uuid-123');
});

it('throws 403 HttpResponseException when user not found in directory', function () {
    $jwtProfile = new JwtProfileDto('unknown-uuid');

    $this->repo->shouldReceive('findByKey')
        ->with('unknown-uuid')
        ->once()
        ->andReturn(null);

    expect(fn () => $this->service->resolveFromJwtProfile($jwtProfile))
        ->toThrow(HttpResponseException::class);
});

it('includes user_not_in_directory error code when user missing from db', function () {
    $jwtProfile = new JwtProfileDto('ghost-uuid');

    $this->repo->shouldReceive('findByKey')
        ->with('ghost-uuid')
        ->andReturn(null);

    try {
        $this->service->resolveFromJwtProfile($jwtProfile);
        $this->fail('Expected HttpResponseException');
    } catch (HttpResponseException $e) {
        $response = $e->getResponse();
        expect($response->getStatusCode())->toBe(403);
        $content = json_decode($response->getContent(), true);
        expect($content['error']['code'])->toBe('user_not_in_directory');
    }
});

describe('JwtProfileDto::fromRequestAttribute', function () {
    it('creates DTO from valid jwt_user array', function () {
        $jwtUser = ['id' => 'user-uuid-123', 'sub' => 'user-uuid-123'];
        $dto = JwtProfileDto::fromRequestAttribute($jwtUser);

        expect($dto)->not->toBeNull();
        expect($dto->id)->toBe('user-uuid-123');
    });

    it('returns null when jwt_user is not an array', function () {
        $dto = JwtProfileDto::fromRequestAttribute('invalid-string');
        expect($dto)->toBeNull();
    });

    it('returns null when jwt_user is null', function () {
        $dto = JwtProfileDto::fromRequestAttribute(null);
        expect($dto)->toBeNull();
    });

    it('returns null when jwt_user has no id', function () {
        $jwtUser = ['sub' => 'some-sub'];
        $dto = JwtProfileDto::fromRequestAttribute($jwtUser);
        expect($dto)->toBeNull();
    });

    it('returns null when jwt_user id is empty string', function () {
        $jwtUser = ['id' => ''];
        $dto = JwtProfileDto::fromRequestAttribute($jwtUser);
        expect($dto)->toBeNull();
    });

    it('returns null when jwt_user id is not a string', function () {
        $jwtUser = ['id' => 123];
        $dto = JwtProfileDto::fromRequestAttribute($jwtUser);
        expect($dto)->toBeNull();
    });
});

<?php

declare(strict_types=1);

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\PanelUserService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

uses(\Tests\TestCase::class);

beforeEach(function () {
    $this->repo = Mockery::mock(UserRepositoryInterface::class);
    $this->service = new PanelUserService($this->repo);
});

afterEach(function () {
    Mockery::close();
});

function makeRequestWithJwt(?array $jwtUser): Request
{
    $request = new Request();
    if ($jwtUser !== null) {
        $request->attributes->set('jwt_user', $jwtUser);
    }
    return $request;
}

it('resolves user from jwt_user attribute in request', function () {
    $user = new User(['id' => 'user-uuid-123', 'name' => 'Test']);
    $request = makeRequestWithJwt(['id' => 'user-uuid-123', 'sub' => 'user-uuid-123']);

    $this->repo->shouldReceive('findByKey')
        ->with('user-uuid-123')
        ->once()
        ->andReturn($user);

    $result = $this->service->resolveFromJwtRequest($request);

    expect($result)->toBe($user);
});

it('throws 403 HttpResponseException when jwt_user is missing', function () {
    $request = new Request(); // no jwt_user attribute

    expect(fn () => $this->service->resolveFromJwtRequest($request))
        ->toThrow(HttpResponseException::class);
});

it('throws 403 HttpResponseException when jwt_user has no id', function () {
    $request = makeRequestWithJwt(['sub' => 'some-sub']); // no 'id'

    expect(fn () => $this->service->resolveFromJwtRequest($request))
        ->toThrow(HttpResponseException::class);
});

it('throws 403 HttpResponseException when jwt_user id is empty string', function () {
    $request = makeRequestWithJwt(['id' => '']);

    expect(fn () => $this->service->resolveFromJwtRequest($request))
        ->toThrow(HttpResponseException::class);
});

it('throws 403 HttpResponseException when user not found in directory', function () {
    $request = makeRequestWithJwt(['id' => 'unknown-uuid']);

    $this->repo->shouldReceive('findByKey')
        ->with('unknown-uuid')
        ->once()
        ->andReturn(null);

    expect(fn () => $this->service->resolveFromJwtRequest($request))
        ->toThrow(HttpResponseException::class);
});

it('throws 403 HttpResponseException when jwt_user is not an array', function () {
    $request = new Request();
    $request->attributes->set('jwt_user', 'invalid-string');

    expect(fn () => $this->service->resolveFromJwtRequest($request))
        ->toThrow(HttpResponseException::class);
});

it('includes actor_missing error code when jwt is absent', function () {
    $request = new Request();

    try {
        $this->service->resolveFromJwtRequest($request);
        $this->fail('Expected HttpResponseException');
    } catch (HttpResponseException $e) {
        $response = $e->getResponse();
        expect($response->getStatusCode())->toBe(403);
        $content = json_decode($response->getContent(), true);
        expect($content['error']['code'])->toBe('actor_missing');
    }
});

it('includes user_not_in_directory error code when user missing from db', function () {
    $request = makeRequestWithJwt(['id' => 'ghost-uuid']);

    $this->repo->shouldReceive('findByKey')
        ->with('ghost-uuid')
        ->andReturn(null);

    try {
        $this->service->resolveFromJwtRequest($request);
        $this->fail('Expected HttpResponseException');
    } catch (HttpResponseException $e) {
        $response = $e->getResponse();
        expect($response->getStatusCode())->toBe(403);
        $content = json_decode($response->getContent(), true);
        expect($content['error']['code'])->toBe('user_not_in_directory');
    }
});

<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Maya\Auth\Middleware\JwtMiddleware;
use Maya\Auth\Middleware\RequirePermissionMiddleware;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    $this->withoutMiddleware([JwtMiddleware::class, RequirePermissionMiddleware::class]);

    $this->userId = (string) Str::uuid();
    $this->user = User::forceCreate([
        'id'         => $this->userId,
        'email'      => 'ecc-test@maya.localhost',
        'name'       => 'ECC User',
        'first_name' => 'ECC',
        'last_name'  => 'User',
        'username'   => 'eccuser',
        'is_active'  => true,
    ]);

    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', ['id' => $userId, 'sub' => $userId]);
    });

    $this->actingAs($this->user);
    Gate::before(function () {
        return true;
    });

    $this->appId = DB::table('applications')->insertGetId([
        'name'       => 'ECC App',
        'slug'       => 'ecc-app-' . Str::random(4),
        'is_active'  => true,
        'created_at' => now(),
    ]);

    $this->errorCodeId = DB::table('error_codes')->insertGetId([
        'application_id' => $this->appId,
        'code'           => 'ERR_ECC',
        'name'           => 'ECC Error',
        'file'           => 'app/ECC.php',
        'line'           => 1,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);
});

// ─── index ────────────────────────────────────────────────────────────────────

it('returns empty comment list for error code with no comments', function () {
    $response = $this->getJson("/api/v1/error-codes/{$this->errorCodeId}/comments");

    $response->assertOk();
    expect($response->json('data'))->toBeArray()
        ->and($response->json('data'))->toHaveCount(0);
});

it('returns comments for an error code', function () {
    DB::table('comments')->insert([
        'commentable_type' => \App\Models\ErrorCode::class,
        'commentable_id'   => $this->errorCodeId,
        'user_id'          => $this->userId,
        'content'          => 'Comment on error code',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $response = $this->getJson("/api/v1/error-codes/{$this->errorCodeId}/comments");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.content'))->toBe('Comment on error code');
});

it('returns 404 when error code does not exist', function () {
    $response = $this->getJson('/api/v1/error-codes/99999/comments');

    $response->assertNotFound();
});

// ─── store ────────────────────────────────────────────────────────────────────

it('creates a comment on an error code', function () {
    $response = $this->postJson("/api/v1/error-codes/{$this->errorCodeId}/comments", [
        'content' => 'New comment on error code',
    ]);

    $response->assertCreated();
    expect($response->json('data.content'))->toBe('New comment on error code');

    $this->assertDatabaseHas('comments', [
        'commentable_id' => $this->errorCodeId,
        'content'        => 'New comment on error code',
    ]);
});

it('returns 422 when comment content is missing', function () {
    $response = $this->postJson("/api/v1/error-codes/{$this->errorCodeId}/comments", []);

    $response->assertUnprocessable();
    expect($response->json('errors'))->toHaveKey('content');
});

it('returns 422 when comment content is too short', function () {
    $response = $this->postJson("/api/v1/error-codes/{$this->errorCodeId}/comments", [
        'content' => 'ab',
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors'))->toHaveKey('content');
});

it('returns 404 when posting comment to non-existent error code', function () {
    $response = $this->postJson('/api/v1/error-codes/99999/comments', [
        'content' => 'This comment has no parent',
    ]);

    $response->assertNotFound();
});

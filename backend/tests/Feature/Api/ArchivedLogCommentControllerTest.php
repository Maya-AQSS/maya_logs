<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maya\Auth\Middleware\JwtMiddleware;
use Maya\Auth\Middleware\RequirePermissionMiddleware;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([JwtMiddleware::class, RequirePermissionMiddleware::class]);

    $this->userId = (string) Str::uuid();
    $this->user = User::forceCreate([
        'id'         => $this->userId,
        'email'      => 'alc-test@maya.localhost',
        'name'       => 'ALC User',
        'first_name' => 'ALC',
        'last_name'  => 'User',
        'username'   => 'alcuser',
        'is_active'  => true,
    ]);

    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', ['id' => $userId, 'sub' => $userId]);
    });

    $this->appId = DB::table('applications')->insertGetId([
        'name'       => 'ALC App',
        'slug'       => 'alc-app-' . Str::random(4),
        'is_active'  => true,
        'created_at' => now(),
    ]);

    $this->archivedLogId = DB::table('archived_logs')->insertGetId([
        'application_id'      => $this->appId,
        'archived_by_id'      => $this->userId,
        'severity'            => 'high',
        'message'             => 'Archived for comment test',
        'metadata'            => json_encode([]),
        'archived_at'         => now(),
        'original_created_at' => now(),
        'updated_at'          => now(),
    ]);
});

// ─── index ────────────────────────────────────────────────────────────────────

it('returns empty comment list for archived log with no comments', function () {
    $response = $this->getJson("/api/v1/archived-logs/{$this->archivedLogId}/comments");

    $response->assertOk();
    expect($response->json('data'))->toBeArray()
        ->and($response->json('data'))->toHaveCount(0);
});

it('returns comments for an archived log', function () {
    DB::table('comments')->insert([
        'commentable_type' => \App\Models\ArchivedLog::class,
        'commentable_id'   => $this->archivedLogId,
        'user_id'          => $this->userId,
        'content'          => 'First comment',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);
    DB::table('comments')->insert([
        'commentable_type' => \App\Models\ArchivedLog::class,
        'commentable_id'   => $this->archivedLogId,
        'user_id'          => $this->userId,
        'content'          => 'Second comment',
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    $response = $this->getJson("/api/v1/archived-logs/{$this->archivedLogId}/comments");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('returns 404 when archived log does not exist', function () {
    $response = $this->getJson('/api/v1/archived-logs/99999/comments');

    $response->assertNotFound();
});

// ─── store ────────────────────────────────────────────────────────────────────

it('creates a comment on an archived log', function () {
    $response = $this->postJson("/api/v1/archived-logs/{$this->archivedLogId}/comments", [
        'content' => 'This is a new comment',
    ]);

    $response->assertCreated();
    // ArchivedLogCommentController::storeResponse devuelve el recurso sin envelope
    // `data` (wire histórico), a diferencia de AbstractCommentController.
    expect($response->json('content'))->toBe('This is a new comment');

    $this->assertDatabaseHas('comments', [
        'commentable_id'   => $this->archivedLogId,
        'content'          => 'This is a new comment',
    ]);
});

it('returns 422 when comment content is missing', function () {
    $response = $this->postJson("/api/v1/archived-logs/{$this->archivedLogId}/comments", []);

    $response->assertUnprocessable();
    expect($response->json('errors'))->toHaveKey('content');
});

it('returns 422 when comment content is too short', function () {
    $response = $this->postJson("/api/v1/archived-logs/{$this->archivedLogId}/comments", [
        'content' => 'ab',
    ]);

    $response->assertUnprocessable();
    expect($response->json('errors'))->toHaveKey('content');
});

it('returns 404 when posting comment to non-existent archived log', function () {
    $response = $this->postJson('/api/v1/archived-logs/99999/comments', [
        'content' => 'This comment has no parent',
    ]);

    $response->assertNotFound();
});

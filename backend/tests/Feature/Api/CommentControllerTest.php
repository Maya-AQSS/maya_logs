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
        'email'      => 'cc-test@maya.localhost',
        'name'       => 'CC User',
        'first_name' => 'CC',
        'last_name'  => 'User',
        'username'   => 'ccuser',
        'is_active'  => true,
    ]);

    $userId = $this->userId;
    $this->app['events']->listen(RouteMatched::class, function ($event) use ($userId) {
        $event->request->attributes->set('jwt_user', ['id' => $userId, 'sub' => $userId]);
    });

    $this->appId = DB::table('applications')->insertGetId([
        'name'       => 'CC App',
        'slug'       => 'cc-app-' . Str::random(4),
        'is_active'  => true,
        'created_at' => now(),
    ]);

    $this->archivedLogId = DB::table('archived_logs')->insertGetId([
        'application_id'      => $this->appId,
        'archived_by_id'      => $this->userId,
        'severity'            => 'high',
        'message'             => 'For comment ctrl test',
        'metadata'            => json_encode([]),
        'archived_at'         => now(),
        'original_created_at' => now(),
        'updated_at'          => now(),
    ]);
});

function makeComment(int $archivedLogId, string $userId, string $content = 'Original comment'): int
{
    return DB::table('comments')->insertGetId([
        'commentable_type' => \App\Models\ArchivedLog::class,
        'commentable_id'   => $archivedLogId,
        'user_id'          => $userId,
        'content'          => $content,
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);
}

// ─── update ──────────────────────────────────────────────────────────────────

it('allows owner to update their comment', function () {
    $commentId = makeComment($this->archivedLogId, $this->userId, 'Old content');

    $response = $this->patchJson("/api/v1/comments/{$commentId}", [
        'content' => 'Updated content here',
    ]);

    $response->assertOk();
    expect($response->json('data.content'))->toBe('Updated content here');

    $this->assertDatabaseHas('comments', ['id' => $commentId, 'content' => 'Updated content here']);
});

it('returns 403 when another user tries to update a comment', function () {
    $otherId = (string) Str::uuid();
    User::forceCreate([
        'id'         => $otherId,
        'email'      => 'other@maya.localhost',
        'name'       => 'Other User',
        'first_name' => 'Other',
        'last_name'  => 'User',
        'username'   => 'otheruser',
        'is_active'  => true,
    ]);
    $commentId = makeComment($this->archivedLogId, $otherId, 'Other user comment');

    $response = $this->patchJson("/api/v1/comments/{$commentId}", [
        'content' => 'Should not succeed',
    ]);

    $response->assertForbidden();
});

it('returns 404 when updating non-existent comment', function () {
    $response = $this->patchJson('/api/v1/comments/99999', [
        'content' => 'Updated content here',
    ]);

    $response->assertNotFound();
});

it('returns 422 when update comment content is missing', function () {
    $commentId = makeComment($this->archivedLogId, $this->userId);

    $response = $this->patchJson("/api/v1/comments/{$commentId}", []);

    $response->assertUnprocessable();
    expect($response->json('errors'))->toHaveKey('content');
});

// ─── destroy ─────────────────────────────────────────────────────────────────

it('allows owner to delete their comment', function () {
    $commentId = makeComment($this->archivedLogId, $this->userId);

    $response = $this->deleteJson("/api/v1/comments/{$commentId}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('comments', ['id' => $commentId]);
});

it('returns 403 when another user tries to delete a comment', function () {
    $otherId = (string) Str::uuid();
    User::forceCreate([
        'id'         => $otherId,
        'email'      => 'other2@maya.localhost',
        'name'       => 'Other User 2',
        'first_name' => 'Other',
        'last_name'  => 'User2',
        'username'   => 'otheruser2',
        'is_active'  => true,
    ]);
    $commentId = makeComment($this->archivedLogId, $otherId);

    $response = $this->deleteJson("/api/v1/comments/{$commentId}");

    $response->assertForbidden();
});

it('returns 404 when deleting non-existent comment', function () {
    $response = $this->deleteJson('/api/v1/comments/99999');

    $response->assertNotFound();
});

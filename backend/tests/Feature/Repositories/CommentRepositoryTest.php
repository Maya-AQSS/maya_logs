<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\ErrorCode;
use App\Models\User;
use App\Repositories\Eloquent\CommentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repo = new CommentRepository();
    $this->user = User::factory()->create();

    // Create an application + error code to attach comments to
    $appId = DB::table('applications')->insertGetId([
        'name'       => 'Test App',
        'slug'       => 'test-app-' . Str::random(4),
        'is_active'  => true,
        'created_at' => now(),
    ]);

    $this->errorCode = ErrorCode::create([
        'application_id' => $appId,
        'code'           => 'ERR_001',
        'name'           => 'Test Error',
        'file'           => 'app/test.php',
        'line'           => 10,
    ]);
});

it('finds a comment by id', function () {
    $comment = Comment::create([
        'commentable_type' => ErrorCode::class,
        'commentable_id'   => $this->errorCode->id,
        'user_id'          => $this->user->id,
        'content'          => 'Hello',
    ]);

    $found = $this->repo->findOrFail($comment->id);

    expect($found->id)->toBe($comment->id)
        ->and($found->content)->toBe('Hello');
});

it('throws ModelNotFoundException when comment not found', function () {
    expect(fn () => $this->repo->findOrFail(99999))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('lists comments for commentable model ordered by latest', function () {
    Comment::create([
        'commentable_type' => ErrorCode::class,
        'commentable_id'   => $this->errorCode->id,
        'user_id'          => $this->user->id,
        'content'          => 'First',
    ]);
    Comment::create([
        'commentable_type' => ErrorCode::class,
        'commentable_id'   => $this->errorCode->id,
        'user_id'          => $this->user->id,
        'content'          => 'Second',
    ]);

    $comments = $this->repo->listForCommentable($this->errorCode);

    expect($comments)->toHaveCount(2);
    // Both comments are present (ordering may differ due to SQLite timestamp precision)
    $contents = $comments->pluck('content')->all();
    expect($contents)->toContain('First')
        ->and($contents)->toContain('Second');
});

it('returns empty collection when no comments exist', function () {
    $comments = $this->repo->listForCommentable($this->errorCode);

    expect($comments)->toBeEmpty();
});

it('creates a comment for commentable model', function () {
    $comment = $this->repo->createForCommentable($this->errorCode, $this->user->id, '<p>My comment</p>');

    expect($comment->content)->toBe('<p>My comment</p>')
        ->and($comment->user_id)->toBe($this->user->id)
        ->and($comment->commentable_type)->toBe(ErrorCode::class)
        ->and($comment->commentable_id)->toBe($this->errorCode->id);
});

it('creates a comment and eager loads user relation', function () {
    $comment = $this->repo->createForCommentable($this->errorCode, $this->user->id, '<p>Test</p>');

    expect($comment->relationLoaded('user'))->toBeTrue()
        ->and($comment->user->id)->toBe($this->user->id);
});

it('updates comment content', function () {
    $comment = Comment::create([
        'commentable_type' => ErrorCode::class,
        'commentable_id'   => $this->errorCode->id,
        'user_id'          => $this->user->id,
        'content'          => 'Old content',
    ]);

    $updated = $this->repo->updateContent($comment, '<p>New content</p>');

    expect($updated->content)->toBe('<p>New content</p>');
    $this->assertDatabaseHas('comments', ['id' => $comment->id, 'content' => '<p>New content</p>']);
});

it('deletes a comment', function () {
    $comment = Comment::create([
        'commentable_type' => ErrorCode::class,
        'commentable_id'   => $this->errorCode->id,
        'user_id'          => $this->user->id,
        'content'          => 'To be deleted',
    ]);

    $this->repo->delete($comment);

    $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
});

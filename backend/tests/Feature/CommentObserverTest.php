<?php

declare(strict_types=1);

use App\Models\ArchivedLog;
use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Maya\Messaging\Publishers\AuditPublisher;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

/**
 * Verifica que CommentObserver publica created/updated/deleted en
 * `maya.audit` con el actor correcto (JWT user del panel cuando hay
 * request, autor del comentario como fallback).
 *
 * No requiere parent commentable (la tabla `comments` no tiene FK física
 * sobre `commentable_id`); usamos UUIDs ficticios.
 */

function withJwtActor(string $userId): void
{
    $request = Request::create('/api/v1/comments', 'POST');
    $request->attributes->set('jwt_user', ['id' => $userId]);
    app()->instance('request', $request);
}

/**
 * @param  array<int, mixed>  $args
 * @return array{applicationSlug: string, entityType: string, entityId: string, action: string, userId: string, blockId: ?string, previousValue: ?array<string, mixed>, newValue: ?array<string, mixed>}
 */
function unpackPublishArgs(array $args): array
{
    $padded = array_pad($args, 8, null);

    return [
        'applicationSlug' => $padded[0],
        'entityType'      => $padded[1],
        'entityId'        => $padded[2],
        'action'          => $padded[3],
        'userId'          => $padded[4],
        'blockId'         => $padded[5],
        'previousValue'   => $padded[6],
        'newValue'        => $padded[7],
    ];
}

beforeEach(function () {
    config([
        'messaging.app'           => 'maya-logs',
        'messaging.audit_enabled' => true,
    ]);

    $this->publisher = Mockery::mock(AuditPublisher::class);
    $this->publisher->shouldReceive('publish')->byDefault();
    $this->app->instance(AuditPublisher::class, $this->publisher);

    withJwtActor('actor-uuid-1');
});

afterEach(function () {
    Mockery::close();
});

it('created publishes to maya.audit with jwt actor', function () {
    $captured = [];
    $this->publisher
        ->shouldReceive('publish')
        ->once()
        ->withArgs(function (...$args) use (&$captured): bool {
            $captured = unpackPublishArgs($args);

            return $captured['action'] === 'created';
        });

    Comment::create([
        'commentable_type' => ArchivedLog::class,
        'commentable_id'   => 1,
        'user_id'          => 'author-uuid-1',
        'content'          => 'Nuevo comentario',
    ]);

    expect($captured['applicationSlug'])->toBe('maya-logs');
    expect($captured['entityType'])->toBe('comment');
    expect($captured['action'])->toBe('created');
    expect($captured['userId'])->toBe('actor-uuid-1');
    expect($captured['previousValue'])->toBeNull();
    expect($captured['newValue'])->toBeArray();
    expect($captured['newValue']['content'] ?? null)->toBe('Nuevo comentario');
});

it('updated publishes diff only', function () {
    $comment = Comment::create([
        'commentable_type' => ArchivedLog::class,
        'commentable_id'   => 1,
        'user_id'          => 'author-uuid-1',
        'content'          => 'Original',
    ]);

    $captured = [];
    $this->publisher
        ->shouldReceive('publish')
        ->once()
        ->withArgs(function (...$args) use (&$captured): bool {
            $captured = unpackPublishArgs($args);

            return $captured['action'] === 'updated';
        });

    $comment->update(['content' => 'Editado']);

    expect($captured['action'])->toBe('updated');
    expect($captured['previousValue']['content'] ?? null)->toBe('Original');
    expect($captured['newValue']['content'] ?? null)->toBe('Editado');
});

it('deleted publishes with previous payload', function () {
    $comment = Comment::create([
        'commentable_type' => ArchivedLog::class,
        'commentable_id'   => 1,
        'user_id'          => 'author-uuid-1',
        'content'          => 'Texto',
    ]);

    $captured = [];
    $this->publisher
        ->shouldReceive('publish')
        ->once()
        ->withArgs(function (...$args) use (&$captured): bool {
            $captured = unpackPublishArgs($args);

            return $captured['action'] === 'deleted';
        });

    $comment->delete();

    expect($captured['action'])->toBe('deleted');
    expect($captured['previousValue']['content'] ?? null)->toBe('Texto');
    expect($captured['newValue'])->toBeNull();
});

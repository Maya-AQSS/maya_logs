<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ArchivedLog;
use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Maya\Messaging\Publishers\AuditPublisher;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Verifica que CommentObserver publica created/updated/deleted en
 * `maya.audit` con el actor correcto (JWT user del panel cuando hay
 * request, autor del comentario como fallback).
 *
 * No requiere parent commentable (la tabla `comments` no tiene FK física
 * sobre `commentable_id`); usamos UUIDs ficticios.
 */
class CommentObserverTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $publisher;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'messaging.app' => 'maya-logs',
            'messaging.audit_enabled' => true,
        ]);

        $this->publisher = Mockery::mock(AuditPublisher::class);
        $this->publisher->shouldReceive('publish')->byDefault();
        $this->app->instance(AuditPublisher::class, $this->publisher);

        $this->withJwtActor('actor-uuid-1');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function withJwtActor(string $userId): void
    {
        $request = Request::create('/api/v1/comments', 'POST');
        $request->attributes->set('jwt_user', ['id' => $userId]);
        $this->app->instance('request', $request);
    }

    /**
     * @param  array<int, mixed>  $args
     * @return array{applicationSlug: string, entityType: string, entityId: string, action: string, userId: string, blockId: ?string, previousValue: ?array<string, mixed>, newValue: ?array<string, mixed>}
     */
    private function unpackPublishArgs(array $args): array
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

    public function test_created_publishes_to_maya_audit_with_jwt_actor(): void
    {
        $captured = [];
        $this->publisher
            ->shouldReceive('publish')
            ->once()
            ->withArgs(function (...$args) use (&$captured): bool {
                $captured = $this->unpackPublishArgs($args);

                return $captured['action'] === 'created';
            });

        Comment::create([
            'commentable_type' => ArchivedLog::class,
            'commentable_id'   => 1,
            'user_id'          => 'author-uuid-1',
            'content'          => 'Nuevo comentario',
        ]);

        $this->assertSame('maya-logs', $captured['applicationSlug']);
        $this->assertSame('comment', $captured['entityType']);
        $this->assertSame('created', $captured['action']);
        $this->assertSame('actor-uuid-1', $captured['userId']);
        $this->assertNull($captured['previousValue']);
        $this->assertIsArray($captured['newValue']);
        $this->assertSame('Nuevo comentario', $captured['newValue']['content'] ?? null);
    }

    public function test_updated_publishes_diff_only(): void
    {
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
                $captured = $this->unpackPublishArgs($args);

                return $captured['action'] === 'updated';
            });

        $comment->update(['content' => 'Editado']);

        $this->assertSame('updated', $captured['action']);
        $this->assertSame('Original', $captured['previousValue']['content'] ?? null);
        $this->assertSame('Editado', $captured['newValue']['content'] ?? null);
    }

    public function test_deleted_publishes_with_previous_payload(): void
    {
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
                $captured = $this->unpackPublishArgs($args);

                return $captured['action'] === 'deleted';
            });

        $comment->delete();

        $this->assertSame('deleted', $captured['action']);
        $this->assertSame('Texto', $captured['previousValue']['content'] ?? null);
        $this->assertNull($captured['newValue']);
    }
}

<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\ErrorCode;
use App\Models\User;
use App\Repositories\Contracts\ArchivedLogRepositoryInterface;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Services\Contracts\CommentContentSanitizerInterface;
use App\Services\CommentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Maya\Messaging\Publishers\LogPublisher;
use Maya\Messaging\Publishers\NotificationPublisher;
use Maya\Messaging\Publishers\ResilientLogPublisher;
use Mews\Purifier\Facades\Purifier;

uses(\Tests\TestCase::class);

beforeEach(function () {
    $this->mockRepo = Mockery::mock(CommentRepositoryInterface::class);
    // ResilientLogPublisher es final → instanciar real con LogPublisher mock
    // (igual que ArchivedLogServiceArchiveEventTest).
    $publisher = new ResilientLogPublisher(Mockery::mock(LogPublisher::class)->shouldIgnoreMissing());
    $this->service  = new CommentService(
        $this->mockRepo,
        $publisher,
        app(CommentContentSanitizerInterface::class),
        Mockery::mock(NotificationPublisher::class)->shouldIgnoreMissing(),
        Mockery::mock(ArchivedLogRepositoryInterface::class)->shouldIgnoreMissing(),
    );
});

afterEach(function () {
    Mockery::close();
});

// ─── helpers ──────────────────────────────────────────────────────────────────

function makeCommentModel(string $userId, string $content, int $id = 1): Comment
{
    $comment = new Comment([
        'user_id'          => $userId,
        'content'          => $content,
        'commentable_type' => ErrorCode::class,
        'commentable_id'   => 1,
    ]);
    $comment->id = $id;

    return $comment;
}

// ─── findOrFail ───────────────────────────────────────────────────────────────

it('findOrFail delegates to repository and returns CommentDto', function () {
    $user    = new User();
    $user->id   = 'user-uuid-1';
    $user->name = 'Test User';

    $comment = makeCommentModel($user->id, '<p>Hello</p>');
    $comment->setRelation('user', $user);

    $this->mockRepo
        ->shouldReceive('findOrFail')
        ->with(1)
        ->once()
        ->andReturn($comment);

    $dto = $this->service->findOrFail(1);

    expect($dto->content)->toBe('<p>Hello</p>');
});

it('findOrFail propagates ModelNotFoundException from repository', function () {
    $this->mockRepo
        ->shouldReceive('findOrFail')
        ->with(99999)
        ->once()
        ->andThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect(fn () => $this->service->findOrFail(99999))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

// ─── findModelOrFail ──────────────────────────────────────────────────────────

it('findModelOrFail returns the Comment model', function () {
    $comment = makeCommentModel('user-1', '<p>Content</p>');

    $this->mockRepo
        ->shouldReceive('findOrFail')
        ->with(1)
        ->once()
        ->andReturn($comment);

    $result = $this->service->findModelOrFail(1);

    expect($result)->toBeInstanceOf(Comment::class)
        ->and($result->content)->toBe('<p>Content</p>');
});

// ─── listForCommentable ───────────────────────────────────────────────────────

it('listForCommentable returns array of CommentDtos', function () {
    $commentable = new ErrorCode();
    $commentable->id = 1;

    $user    = new User();
    $user->id   = 'user-uuid-1';
    $user->name = 'Test User';

    $c1 = makeCommentModel($user->id, '<p>First</p>', 1);
    $c1->setRelation('user', $user);
    $c2 = makeCommentModel($user->id, '<p>Second</p>', 2);
    $c2->setRelation('user', $user);

    $this->mockRepo
        ->shouldReceive('listForCommentable')
        ->with(ErrorCode::class, $commentable->id)
        ->once()
        ->andReturn(new Collection([$c1, $c2]));

    $result = $this->service->listForCommentable(ErrorCode::class, $commentable->id);

    expect($result)->toHaveCount(2)
        ->and($result[0]->content)->toBe('<p>First</p>')
        ->and($result[1]->content)->toBe('<p>Second</p>');
});

it('listForCommentable returns empty array when no comments', function () {
    $commentable = new ErrorCode();
    $commentable->id = 1;

    $this->mockRepo
        ->shouldReceive('listForCommentable')
        ->with(ErrorCode::class, $commentable->id)
        ->once()
        ->andReturn(new Collection([]));

    $result = $this->service->listForCommentable(ErrorCode::class, $commentable->id);

    expect($result)->toBeEmpty();
});

// ─── createForCommentable ─────────────────────────────────────────────────────

it('createForCommentable sanitizes content and delegates to repository', function () {
    $commentable = new ErrorCode();
    $commentable->id = 1;
    $userId = 'user-uuid-1';

    $user    = new User();
    $user->id   = $userId;
    $user->name = 'Test User';

    $storedComment = makeCommentModel($userId, '<p>Valid content</p>');
    $storedComment->setRelation('user', $user);

    Purifier::shouldReceive('clean')
        ->with('<p>Valid content</p>', 'rich_comment')
        ->once()
        ->andReturn('<p>Valid content</p>');

    $this->mockRepo
        ->shouldReceive('createForCommentable')
        ->with(ErrorCode::class, $commentable->id, $userId,'<p>Valid content</p>')
        ->once()
        ->andReturn($storedComment);

    $dto = $this->service->createForCommentable(ErrorCode::class, $commentable->id,$userId, '<p>Valid content</p>');

    expect($dto->content)->toBe('<p>Valid content</p>');
});

it('createForCommentable throws ValidationException when content is blank after sanitization', function () {
    $commentable = new ErrorCode();
    $commentable->id = 1;

    Purifier::shouldReceive('clean')
        ->andReturn('<p>   </p>');

    $this->mockRepo->shouldNotReceive('createForCommentable');

    expect(fn () => $this->service->createForCommentable(ErrorCode::class, $commentable->id,'user-1', '<p>   </p>'))
        ->toThrow(ValidationException::class);
});

it('createForCommentable throws ValidationException when content exceeds 10MB', function () {
    $commentable = new ErrorCode();
    $commentable->id = 1;

    $bigContent = '<p>' . str_repeat('a', 10 * 1024 * 1024 + 1) . '</p>';

    Purifier::shouldReceive('clean')
        ->andReturn($bigContent);

    $this->mockRepo->shouldNotReceive('createForCommentable');

    expect(fn () => $this->service->createForCommentable(ErrorCode::class, $commentable->id,'user-1', $bigContent))
        ->toThrow(ValidationException::class);
});

it('createForCommentable throws ValidationException for invalid base64 image', function () {
    $commentable = new ErrorCode();
    $commentable->id = 1;

    $html = '<img src="data:image/png;base64,!!!invalid!!!">';

    Purifier::shouldReceive('clean')
        ->andReturn($html);

    $this->mockRepo->shouldNotReceive('createForCommentable');

    expect(fn () => $this->service->createForCommentable(ErrorCode::class, $commentable->id,'user-1', $html))
        ->toThrow(ValidationException::class);
});

it('createForCommentable throws ValidationException for embedded image exceeding 2MB', function () {
    $commentable = new ErrorCode();
    $commentable->id = 1;

    $largeImage = str_repeat("\x89PNG", 600_000); // >2MB
    $encoded    = base64_encode($largeImage);
    $html       = '<img src="data:image/png;base64,' . $encoded . '">';

    Purifier::shouldReceive('clean')
        ->andReturn($html);

    $this->mockRepo->shouldNotReceive('createForCommentable');

    expect(fn () => $this->service->createForCommentable(ErrorCode::class, $commentable->id,'user-1', $html))
        ->toThrow(ValidationException::class);
});

it('createForCommentable throws ValidationException for disallowed image magic bytes', function () {
    $commentable = new ErrorCode();
    $commentable->id = 1;

    $bmpData = str_pad('BM', 20, "\x00"); // BMP — not allowed
    $encoded = base64_encode($bmpData);
    $html    = '<img src="data:image/png;base64,' . $encoded . '">';

    Purifier::shouldReceive('clean')
        ->andReturn($html);

    $this->mockRepo->shouldNotReceive('createForCommentable');

    expect(fn () => $this->service->createForCommentable(ErrorCode::class, $commentable->id,'user-1', $html))
        ->toThrow(ValidationException::class);
});

it('createForCommentable passes for content with only an img tag', function () {
    $commentable = new ErrorCode();
    $commentable->id = 1;
    $userId = 'user-1';

    $pngData = "\x89PNG" . str_repeat("\x00", 20);
    $encoded = base64_encode($pngData);
    $html    = '<img src="data:image/png;base64,' . $encoded . '">';

    $user    = new User();
    $user->id   = $userId;
    $user->name = 'User';

    $stored = makeCommentModel($userId, $html);
    $stored->setRelation('user', $user);

    Purifier::shouldReceive('clean')
        ->andReturn($html);

    $this->mockRepo
        ->shouldReceive('createForCommentable')
        ->with(ErrorCode::class, $commentable->id, $userId,$html)
        ->once()
        ->andReturn($stored);

    $dto = $this->service->createForCommentable(ErrorCode::class, $commentable->id,$userId, $html);

    expect($dto->content)->toBe($html);
});

// ─── updateContent ────────────────────────────────────────────────────────────

it('updateContent sanitizes and delegates to repository', function () {
    $userId = 'user-uuid-1';

    $user    = new User();
    $user->id   = $userId;
    $user->name = 'User';

    $comment = makeCommentModel($userId, '<p>Old</p>');
    $updated = makeCommentModel($userId, '<p>New content</p>');
    $updated->setRelation('user', $user);

    Purifier::shouldReceive('clean')
        ->with('<p>New content</p>', 'rich_comment')
        ->once()
        ->andReturn('<p>New content</p>');

    $this->mockRepo
        ->shouldReceive('updateContent')
        ->with($comment->id,'<p>New content</p>')
        ->once()
        ->andReturn($updated);

    $dto = $this->service->updateContent($comment->id,'<p>New content</p>');

    expect($dto->content)->toBe('<p>New content</p>');
});

it('updateContent throws ValidationException when new content is blank', function () {
    $comment = makeCommentModel('user-1', '<p>Old content</p>');

    Purifier::shouldReceive('clean')
        ->andReturn('');

    $this->mockRepo->shouldNotReceive('updateContent');

    expect(fn () => $this->service->updateContent($comment->id,''))
        ->toThrow(ValidationException::class);
});

// ─── delete ───────────────────────────────────────────────────────────────────

it('delete delegates to repository', function () {
    $comment = makeCommentModel('user-1', '<p>To delete</p>');

    $this->mockRepo
        ->shouldReceive('delete')
        ->with($comment->id)
        ->once();

    $this->service->delete($comment->id);

    // If no exception, the delete was called
    expect(true)->toBeTrue();
});

<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\CommentDto;
use App\Models\Comment;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Services\Contracts\CommentContentSanitizerInterface;
use App\Services\Contracts\CommentServiceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Maya\Messaging\Publishers\ResilientLogPublisher;
use Throwable;

final class CommentService implements CommentServiceInterface
{
    private const CODE_NOT_FOUND = 'LAR-LOG-014';

    private const CODE_CREATE_FAILED = 'LAR-LOG-015';

    private const CODE_UPDATE_FAILED = 'LAR-LOG-016';

    private const CODE_DELETE_FAILED = 'LAR-LOG-017';

    public function __construct(
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly ResilientLogPublisher $resilientLogPublisher,
        private readonly CommentContentSanitizerInterface $contentSanitizer,
    ) {}

    private function messagingAppSlug(): string
    {
        return (string) config('messaging.app');
    }

    public function findOrFail(int $id): CommentDto
    {
        return CommentDto::fromModel($this->findModelOrFail($id));
    }

    /**
     * Sin telemetría en listados (evita ruido); solo se publica a maya.logs si falla la carga por id.
     */
    public function findModelOrFail(int $id): Comment
    {
        try {
            return $this->commentRepository->findOrFail($id);
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                self::CODE_NOT_FOUND,
                ['comment_id' => $id],
                $this->messagingAppSlug(),
            );
            throw $e;
        }
    }

    public function listForCommentable(Model $commentable): array
    {
        $comments = $this->commentRepository->listForCommentable($commentable);

        return $comments
            ->map(static fn (Comment $c): CommentDto => CommentDto::fromModel($c))
            ->values()
            ->all();
    }

    public function createForCommentable(Model $commentable, string $userId, string $rawContent): CommentDto
    {
        $sanitized = $this->contentSanitizer->sanitize($rawContent);

        try {
            $comment = $this->commentRepository->createForCommentable($commentable, $userId, $sanitized);
            $comment->loadMissing('user');

            return CommentDto::fromModel($comment);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                self::CODE_CREATE_FAILED,
                [
                    'commentable_type' => $commentable::class,
                    'commentable_id' => $commentable->getKey(),
                ],
                $this->messagingAppSlug(),
            );
            throw $e;
        }
    }

    public function updateContent(Comment $comment, string $rawContent): CommentDto
    {
        $sanitized = $this->contentSanitizer->sanitize($rawContent);

        try {
            $updated = $this->commentRepository->updateContent($comment, $sanitized);
            $updated->loadMissing('user');

            return CommentDto::fromModel($updated);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                self::CODE_UPDATE_FAILED,
                ['comment_id' => $comment->id],
                $this->messagingAppSlug(),
            );
            throw $e;
        }
    }

    /**
     * Elimina un comentario.
     */
    public function delete(Comment $comment): void
    {
        try {
            $this->commentRepository->delete($comment);
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                self::CODE_DELETE_FAILED,
                ['comment_id' => $comment->id],
                $this->messagingAppSlug(),
            );
            throw $e;
        }
    }

}

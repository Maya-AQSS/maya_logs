<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\CommentDto;
use App\Models\ArchivedLog;
use App\Models\Comment;
use App\Repositories\Contracts\ArchivedLogRepositoryInterface;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Services\Contracts\CommentContentSanitizerInterface;
use App\Services\Contracts\CommentServiceInterface;
use Illuminate\Validation\ValidationException;
use Maya\Messaging\Publishers\NotificationPublisher;
use Maya\Messaging\Publishers\ResilientLogPublisher;
use Maya\Messaging\Support\MessagingConfig;
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
        private readonly NotificationPublisher $notificationPublisher,
        private readonly ArchivedLogRepositoryInterface $archivedLogRepository,
    ) {}

    /**
     * Sin telemetría en listados (evita ruido); solo se publica a maya.logs si falla la carga por id.
     */
    public function findOrFail(int $id): CommentDto
    {
        try {
            return CommentDto::fromModel($this->commentRepository->findOrFail($id));
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                self::CODE_NOT_FOUND,
                ['comment_id' => $id],
                MessagingConfig::appSlug(),
            );
            throw $e;
        }
    }

    public function listForCommentable(string $commentableType, int $commentableId): array
    {
        $comments = $this->commentRepository->listForCommentable($commentableType, $commentableId);

        return $comments
            ->map(static fn (Comment $c): CommentDto => CommentDto::fromModel($c))
            ->values()
            ->all();
    }

    public function createForCommentable(string $commentableType, int $commentableId, string $userId, string $rawContent): CommentDto
    {
        $sanitized = $this->contentSanitizer->sanitize($rawContent);

        try {
            $comment = $this->commentRepository->createForCommentable(
                $commentableType,
                $commentableId,
                $userId,
                $sanitized,
            );

            $this->notifyLogOwner($commentableType, $commentableId, $userId);

            return CommentDto::fromModel($comment);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                self::CODE_CREATE_FAILED,
                [
                    'commentable_type' => $commentableType,
                    'commentable_id' => $commentableId,
                ],
                MessagingConfig::appSlug(),
            );
            throw $e;
        }
    }

    /**
     * Notifies the ArchivedLog owner when a different user adds a comment.
     * Wrapped in try/catch so a notification failure never breaks comment creation.
     */
    private function notifyLogOwner(string $commentableType, int $commentableId, string $commentAuthorId): void
    {
        // Only notify for ArchivedLog commentables
        if ($commentableType !== ArchivedLog::class) {
            return;
        }

        try {
            $archivedLog = $this->archivedLogRepository->findOrFail($commentableId);
        } catch (Throwable) {
            return;
        }

        $ownerId = $archivedLog->archived_by_id;

        if ($ownerId === null || $ownerId === $commentAuthorId) {
            return;
        }

        try {
            $this->notificationPublisher->send(
                type: 'log.comment_added',
                recipientId: (string) $ownerId,
                title: 'Nuevo comentario en tu log',
                body: sprintf('Se ha añadido un comentario en el log "%s"', $archivedLog->message ?? ''),
                channels: ['app'],
                metadata: ['log_id' => $archivedLog->getKey()],
                titleKey: 'notifications.log.comment_added.title',
                bodyKey: 'notifications.log.comment_added.body',
                params: ['log_id' => $archivedLog->getKey()],
                severity: 'info',
            );
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'low',
                'LAR-LOG-NOTIF-001',
                [
                    'commentable_id' => $commentableId,
                    'owner_id' => $ownerId,
                ],
                MessagingConfig::appSlug(),
            );
        }
    }

    public function updateContent(int $id, string $rawContent): CommentDto
    {
        $sanitized = $this->contentSanitizer->sanitize($rawContent);

        try {
            $updated = $this->commentRepository->updateContent($id, $sanitized);

            return CommentDto::fromModel($updated);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                self::CODE_UPDATE_FAILED,
                ['comment_id' => $id],
                MessagingConfig::appSlug(),
            );
            throw $e;
        }
    }

    /**
     * Elimina un comentario.
     */
    public function delete(int $id): void
    {
        try {
            $this->commentRepository->delete($id);
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                self::CODE_DELETE_FAILED,
                ['comment_id' => $id],
                MessagingConfig::appSlug(),
            );
            throw $e;
        }
    }
}

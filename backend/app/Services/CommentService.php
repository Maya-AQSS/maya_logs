<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\CommentDto;
use App\Models\Comment;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Services\Contracts\CommentServiceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Maya\Messaging\Publishers\ResilientLogPublisher;
use Mews\Purifier\Facades\Purifier;
use Throwable;

final class CommentService implements CommentServiceInterface
{
    private const MAX_COMMENT_BYTES = 10 * 1024 * 1024;

    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

    private const CODE_NOT_FOUND = 'LAR-LOG-014';

    private const CODE_CREATE_FAILED = 'LAR-LOG-015';

    private const CODE_UPDATE_FAILED = 'LAR-LOG-016';

    private const CODE_DELETE_FAILED = 'LAR-LOG-017';

    public function __construct(
        private readonly CommentRepositoryInterface $commentRepository,
        private readonly ResilientLogPublisher $resilientLogPublisher,
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
        $sanitized = $this->sanitizeAndValidateContent($rawContent);

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
        $sanitized = $this->sanitizeAndValidateContent($rawContent);

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

    /**
     * Sanitiza y valida el contenido de un comentario.
     */
    private function sanitizeAndValidateContent(string $rawContent): string
    {
        $sanitized = Purifier::clean($rawContent, 'rich_comment');

        $this->validateNotBlank($sanitized);
        $this->validateContentSize($sanitized);
        $this->validateEmbeddedImages($sanitized);

        return $sanitized;
    }

    /**
     * Valida que el contenido no esté en blanco.
     */
    private function validateNotBlank(string $html): void
    {
        $textOnly = trim(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], ' ', $html)));

        if ($textOnly !== '' || str_contains($html, '<img')) {
            return;
        }

        throw ValidationException::withMessages([
            'content' => __('validation.required', ['attribute' => 'content']),
        ]);
    }

    /**
     * Valida que el contenido no sea demasiado grande.
     */
    private function validateContentSize(string $html): void
    {
        if (strlen($html) <= self::MAX_COMMENT_BYTES) {
            return;
        }

        throw ValidationException::withMessages([
            'content' => __('comments.editor.comment_too_large'),
        ]);
    }

    /**
     * Valida que las imágenes incrustadas no sean demasiado grandes.
     */
    private function validateEmbeddedImages(string $html): void
    {
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches);
        $sources = $matches[1] ?? [];

        foreach ($sources as $src) {
            if (preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/s', $src, $parts) !== 1) {
                continue;
            }

            $decoded = base64_decode($parts[2], true);
            if ($decoded === false) {
                throw ValidationException::withMessages([
                    'content' => __('comments.editor.image_invalid_type'),
                ]);
            }

            if (strlen($decoded) > self::MAX_IMAGE_BYTES) {
                throw ValidationException::withMessages([
                    'content' => __('comments.editor.image_too_large'),
                ]);
            }

            if (! $this->isAllowedImageByMagicBytes($decoded)) {
                throw ValidationException::withMessages([
                    'content' => __('comments.editor.image_invalid_type'),
                ]);
            }
        }
    }

    /**
     * Valida que las imágenes incrustadas sean de un tipo permitido.
     */
    private function isAllowedImageByMagicBytes(string $binary): bool
    {
        $header = substr($binary, 0, 12);

        if (str_starts_with($header, "\x89PNG")) {
            return true;
        }

        if (str_starts_with($header, "\xFF\xD8\xFF")) {
            return true;
        }

        if (str_starts_with($header, 'GIF8')) {
            return true;
        }

        return str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WEBP';
    }
}

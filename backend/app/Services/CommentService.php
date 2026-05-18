<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\CommentDto;
use App\Models\Comment;
use App\Repositories\Contracts\CommentRepositoryInterface;
use App\Services\Contracts\CommentServiceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Mews\Purifier\Facades\Purifier;

final class CommentService implements CommentServiceInterface
{
    private const MAX_COMMENT_BYTES = 10 * 1024 * 1024;

    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

    public function __construct(
        private readonly CommentRepositoryInterface $commentRepository,
    ) {}

    public function findOrFail(int $id): CommentDto
    {
        return CommentDto::fromModel($this->findModelOrFail($id));
    }

    public function findModelOrFail(int $id): Comment
    {
        return $this->commentRepository->findOrFail($id);
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

        $comment = $this->commentRepository->createForCommentable($commentable, $userId, $sanitized);
        $comment->loadMissing('user');

        return CommentDto::fromModel($comment);
    }

    public function updateContent(Comment $comment, string $rawContent): CommentDto
    {
        $sanitized = $this->sanitizeAndValidateContent($rawContent);

        $updated = $this->commentRepository->updateContent($comment, $sanitized);
        $updated->loadMissing('user');

        return CommentDto::fromModel($updated);
    }

    /**
     * Elimina un comentario.
     */
    public function delete(Comment $comment): void
    {
        $this->commentRepository->delete($comment);
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

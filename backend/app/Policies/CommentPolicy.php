<?php

declare(strict_types=1);

namespace App\Policies;

use App\Dtos\CommentDto;
use App\Models\ArchivedLog;
use App\Models\ErrorCode;
use App\Models\User;
use Illuminate\Http\Request;
use Maya\Profile\Services\Contracts\UserProfileServiceInterface;

/**
 * Editar: solo el autor ({@code user_id}).
 * Borrar: el autor del comentario o quien tenga el slug de módulo
 * ({@code archived-logs.comment.delete}, {@code error-code.comment.delete}, etc.).
 *
 * La policy opera sobre {@see CommentDto} (no sobre el modelo Eloquent): ningún
 * modelo cruza la frontera Service→Controller (Opción A — DTO estricto).
 */
class CommentPolicy
{
    public const ARCHIVED_LOG_COMMENT_DELETE = 'archived-logs.comment.delete';

    public const ERROR_CODE_COMMENT_DELETE = 'error-code.comment.delete';

    public function __construct(
        private readonly Request $request,
        private readonly UserProfileServiceInterface $profileService,
    ) {}

    public function delete(User $user, CommentDto $comment): bool
    {
        if ($user->id === $comment->authorId) {
            return true;
        }

        return $this->userHasDeletePermission($comment);
    }

    public function update(User $user, CommentDto $comment): bool
    {
        return $user->id === $comment->authorId;
    }

    private function userHasDeletePermission(CommentDto $comment): bool
    {
        $slug = match ($comment->commentableType) {
            ArchivedLog::class => self::ARCHIVED_LOG_COMMENT_DELETE,
            ErrorCode::class => self::ERROR_CODE_COMMENT_DELETE,
            default => null,
        };

        if ($slug === null) {
            return false;
        }

        $jwtProfile = (array) $this->request->attributes->get('jwt_user', []);
        $userId = (string) ($jwtProfile['id'] ?? '');
        if ($userId === '') {
            return false;
        }

        $profile = $this->profileService->getProfile($userId, $jwtProfile);
        $permissions = $profile->extra['permissions'] ?? null;

        return is_array($permissions) && in_array($slug, $permissions, true);
    }
}

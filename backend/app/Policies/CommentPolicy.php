<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ArchivedLog;
use App\Models\Comment;
use App\Models\ErrorCode;
use App\Models\User;
use Illuminate\Http\Request;
use Maya\Profile\Services\Contracts\UserProfileServiceInterface;

/**
 * Editar / borrar comentarios: solo el autor ({@code user_id}).
 * Borrar además exige el slug de módulo ({@code archived-logs.comment.delete}, etc.).
 */
class CommentPolicy
{
    public const ARCHIVED_LOG_COMMENT_DELETE = 'archived-logs.comment.delete';

    public const ERROR_CODE_COMMENT_DELETE = 'error-code.comment.delete';

    public function __construct(
        private readonly Request $request,
        private readonly UserProfileServiceInterface $profileService,
    ) {}

    public function delete(User $user, Comment $comment): bool
    {
        if ($user->id !== $comment->user_id) {
            return false;
        }

        return $this->userHasDeletePermission($comment);
    }

    public function update(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id;
    }

    private function userHasDeletePermission(Comment $comment): bool
    {
        $slug = match ($comment->commentable_type) {
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

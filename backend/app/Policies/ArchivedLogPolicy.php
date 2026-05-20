<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ArchivedLog;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Http\Request;
use Maya\Profile\Services\Contracts\UserProfileServiceInterface;

/**
 * Mutaciones sobre logs archivados: permisos desde {@code GET /me}
 * ({@code extra.permissions}), coherente con middleware de rutas.
 */
class ArchivedLogPolicy
{
    public const UPDATE_PERMISSION_CODE = 'archived-logs.update';

    public const DELETE_PERMISSION_CODE = 'archived-logs.delete';

    public function __construct(
        private readonly Request $request,
        private readonly UserProfileServiceInterface $profileService,
    ) {}

    public function update(?User $user, ArchivedLog $archivedLog): Response
    {
        return $this->responseForSlug(self::UPDATE_PERMISSION_CODE);
    }

    public function delete(?User $user, ArchivedLog $archivedLog): Response
    {
        return $this->responseForSlug(self::DELETE_PERMISSION_CODE);
    }

    private function responseForSlug(string $permissionSlug): Response
    {
        [$userId, $jwtProfile] = $this->jwtContext();
        if ($userId === '') {
            return Response::deny(__('api.error_codes.forbidden'), 'archived_logs_permission_denied')->withStatus(403);
        }

        $profile = $this->profileService->getProfile($userId, $jwtProfile);
        $permissions = $profile->extra['permissions'] ?? null;
        if (! is_array($permissions)) {
            return Response::deny(__('api.error_codes.forbidden'), 'archived_logs_permission_denied')->withStatus(403);
        }

        if (in_array($permissionSlug, $permissions, true)) {
            return Response::allow();
        }

        return Response::deny(__('api.error_codes.forbidden'), 'archived_logs_permission_denied')->withStatus(403);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function jwtContext(): array
    {
        $jwtProfile = (array) $this->request->attributes->get('jwt_user', []);
        $userId = (string) ($jwtProfile['id'] ?? '');

        return [$userId, $jwtProfile];
    }
}

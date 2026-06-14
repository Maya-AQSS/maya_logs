<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ResolvesJwtContext;
use Illuminate\Auth\Access\Response;
use Illuminate\Http\Request;
use Maya\Profile\Services\Contracts\UserProfileServiceInterface;

/**
 * Mutaciones sobre logs archivados: permisos desde {@code GET /me}
 * ({@code extra.permissions}), coherente con middleware de rutas.
 *
 * La autorización depende solo del contexto JWT (permisos del actor), no del
 * registro concreto, por lo que el controlador autoriza por clase
 * ({@code authorize('update', ArchivedLog::class)}) y ningún modelo Eloquent
 * cruza la frontera Service→Controller (Opción A — DTO estricto).
 */
class ArchivedLogPolicy
{
    use ResolvesJwtContext;

    public const UPDATE_PERMISSION_CODE = 'archived-logs.update';

    public const DELETE_PERMISSION_CODE = 'archived-logs.delete';

    public function __construct(
        private readonly Request $request,
        private readonly UserProfileServiceInterface $profileService,
    ) {}

    public function update(?User $user): Response
    {
        return $this->responseForSlug(self::UPDATE_PERMISSION_CODE);
    }

    public function delete(?User $user): Response
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
}

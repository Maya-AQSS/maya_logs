<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ErrorCode;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Http\Request;
use Maya\Profile\Services\Contracts\UserProfileServiceInterface;

/**
 * Alta / edición / baja de códigos de error: permisos desde el mismo flujo
 * que {@code GET /me} — {@see UserProfileServiceInterface::getProfile()}
 * (resolver FDW + {@code extra.permissions}), no desde claims arbitrarios del JWT.
 *
 * Coherente con middleware en rutas API ({@see self::CREATE_PERMISSION_CODE},
 * {@see self::UPDATE_PERMISSION_CODE}, {@see self::DELETE_PERMISSION_CODE}).
 */
class ErrorCodePolicy
{
    public const CREATE_PERMISSION_CODE = 'error-code.create';

    public const UPDATE_PERMISSION_CODE = 'error-code.update';

    public const DELETE_PERMISSION_CODE = 'error-code.delete';

    public function __construct(
        private readonly Request $request,
        private readonly UserProfileServiceInterface $profileService,
    ) {}

    public function create(?User $user): Response
    {
        return $this->responseForSlug(self::CREATE_PERMISSION_CODE);
    }

    public function update(?User $user, ErrorCode $errorCode): Response
    {
        return $this->responseForSlug(self::UPDATE_PERMISSION_CODE);
    }

    public function delete(?User $user, ErrorCode $errorCode): Response
    {
        return $this->responseForSlug(self::DELETE_PERMISSION_CODE);
    }

    private function responseForSlug(string $permissionSlug): Response
    {
        [$userId, $jwtProfile] = $this->jwtContext();
        if ($userId === '') {
            return Response::deny(__('api.error_codes.forbidden'), 'error_codes_permission_denied')->withStatus(403);
        }

        $profile = $this->profileService->getProfile($userId, $jwtProfile);
        $permissions = $profile->extra['permissions'] ?? null;
        if (! is_array($permissions)) {
            return Response::deny(__('api.error_codes.forbidden'), 'error_codes_permission_denied')->withStatus(403);
        }

        if (in_array($permissionSlug, $permissions, true)) {
            return Response::allow();
        }

        return Response::deny(__('api.error_codes.forbidden'), 'error_codes_permission_denied')->withStatus(403);
    }

    /**
     * Mismo criterio que el contexto JWT del request.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function jwtContext(): array
    {
        $jwtProfile = (array) $this->request->attributes->get('jwt_user', []);
        $userId = (string) ($jwtProfile['id'] ?? '');

        return [$userId, $jwtProfile];
    }
}

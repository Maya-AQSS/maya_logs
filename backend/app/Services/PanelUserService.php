<?php

declare(strict_types=1);

namespace App\Services;

use Maya\Auth\Dtos\JwtProfileDto;
use App\Dtos\UserRefDto;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Resuelve el usuario del directorio (vista FDW `users`) a partir del JWT.
 */
final class PanelUserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Resuelve el usuario del directorio a partir del JWT profile DTO.
     * Si no existe en `users`, lanza una excepción HTTP 403 con un JSON de error.
     *
     * @throws HttpResponseException 403 JSON si no existe en `users`.
     */
    public function resolveFromJwtProfile(JwtProfileDto $jwtProfile): UserRefDto
    {
        $user = $this->userRepository->findByKey($jwtProfile->id);
        if ($user === null) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'user_not_in_directory',
                    'message' => __('api.comments.actor_not_in_directory'),
                ],
            ], 403));
        }

        return $user;
    }
}

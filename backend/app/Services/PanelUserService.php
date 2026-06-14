<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\UserRefDto;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Maya\Auth\Dtos\JwtProfileDto;

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

    /**
     * Resuelve el ACTOR de la petición como Authenticatable para {@see Gate::forUser()}.
     *
     * No es una entidad de dominio que cruce la frontera Service→Controller: es el
     * sujeto autenticado, requisito estándar de Laravel para evaluar policies. La
     * autorización en sí opera sobre el DTO de dominio (Opción A — DTO estricto).
     */
    public function resolveActorAuthenticatable(string $id): ?User
    {
        return $this->userRepository->findAuthenticatableByKey($id);
    }
}

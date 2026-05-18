<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * Resuelve el {@see User} del directorio (vista FDW `users`) a partir del JWT inyectado en la petición.
 */
final class PanelUserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Resuelve el {@see User} del directorio (vista FDW `users`) a partir del JWT inyectado en la petición.
     * Si falta actor en token o no existe en `users`, lanza una excepción HTTP 403 con un JSON de error.
     *
     * @throws HttpResponseException 403 JSON si falta actor en token o no existe en `users`.
     */
    public function resolveFromJwtRequest(Request $request): User
    {
        /** @var array<string, mixed>|null $jwtUser */
        $jwtUser = $request->attributes->get('jwt_user');
        $jwtSubject = is_array($jwtUser) ? ($jwtUser['id'] ?? null) : null;

        if (! is_string($jwtSubject) || $jwtSubject === '') {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'actor_missing',
                    'message' => __('logs.actor_missing'),
                ],
            ], 403));
        }

        $user = $this->userRepository->findByKey($jwtSubject);
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

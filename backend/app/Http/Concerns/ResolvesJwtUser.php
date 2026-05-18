<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Traduce el claim `sub` del JWT depositado por `JwtMiddleware` en el modelo
 * Eloquent `User` correspondiente.
 *
 * Centraliza la lógica que antes vivía duplicada en CommentController,
 * ArchivedLogController y LogController.
 */
trait ResolvesJwtUser
{
    /**
     * Devuelve el usuario autenticado o lanza 403 si no existe en la BD local.
     */
    protected function resolveJwtUserOrFail(Request $request): User
    {
        $user = $this->resolveJwtUser($request);

        if ($user === null) {
            throw new AccessDeniedHttpException(__('logs.not_authorized'));
        }

        return $user;
    }

    /**
     * Devuelve el usuario autenticado o `null` si no se puede mapear.
     */
    protected function resolveJwtUser(Request $request): ?User
    {
        /** @var array<string, mixed>|null $jwtUser */
        $jwtUser = $request->attributes->get('jwt_user');
        $externalId = is_array($jwtUser) ? ($jwtUser['id'] ?? null) : null;

        if (! is_string($externalId) || $externalId === '') {
            return null;
        }

        return User::find($externalId);
    }
}

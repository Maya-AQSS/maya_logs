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
        $externalId = $this->resolveJwtSubject($request);

        if ($externalId === null) {
            return null;
        }

        return User::find($externalId);
    }

    /**
     * Devuelve el subject (external_id / sub del JWT) o `null` si no está presente.
     * Útil cuando se necesita el id del actor sin hacer un lookup en BD.
     */
    protected function resolveJwtSubject(Request $request): ?string
    {
        /** @var array<string, mixed>|null $jwtUser */
        $jwtUser = $request->attributes->get('jwt_user');
        $externalId = is_array($jwtUser) ? ($jwtUser['id'] ?? null) : null;

        return is_string($externalId) && $externalId !== '' ? $externalId : null;
    }
}

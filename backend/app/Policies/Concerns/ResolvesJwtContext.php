<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use Illuminate\Http\Request;

/**
 * Extrae el contexto JWT del request (perfil + subject id) para las policies.
 *
 * Asume que la clase consumidora expone `$this->request` (Request inyectado),
 * coherente con el resto del flujo de auth (`jwt_user` lo materializa el
 * middleware de validación de token).
 */
trait ResolvesJwtContext
{
    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function jwtContext(): array
    {
        /** @var Request $request */
        $request = $this->request;

        $jwtProfile = (array) $request->attributes->get('jwt_user', []);
        $userId = (string) ($jwtProfile['id'] ?? '');

        return [$userId, $jwtProfile];
    }
}

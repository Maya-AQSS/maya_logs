<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    /**
     * Busca por clave primaria (`users.id`, UUID Keycloak / Odoo FDW).
     */
    public function findByKey(string $id): ?User;
}

<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Dtos\UserRefDto;

interface UserRepositoryInterface
{
    /**
     * Busca por clave primaria (`users.id`, UUID Keycloak / Odoo FDW).
     */
    public function findByKey(string $id): ?UserRefDto;
}

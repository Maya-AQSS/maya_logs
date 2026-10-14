<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Dtos\UserRefDto;
use App\Models\User;

interface UserRepositoryInterface
{
    /**
     * Busca por clave primaria (`users.id` = `sub` de Keycloak = res_users.oauth_uid, FDW Odoo).
     */
    public function findByKey(string $id): ?UserRefDto;

    /**
     * Devuelve el modelo User SOLO para Gate::forUser (requiere Authenticatable).
     * Para cualquier otro read path usar {@see self::findByKey()} (DTO).
     */
    public function findAuthenticatableByKey(string $id): ?User;
}

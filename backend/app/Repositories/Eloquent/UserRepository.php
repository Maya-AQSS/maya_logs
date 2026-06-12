<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Dtos\UserRefDto;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Busca un usuario por su clave primaria (id).
     */
    public function findByKey(string $id): ?UserRefDto
    {
        $user = User::query()->whereKey($id)->first();

        return $user !== null ? UserRefDto::fromModel($user) : null;
    }

    /**
     * Modelo User solo para el policy gate (Gate::forUser exige Authenticatable).
     */
    public function findAuthenticatableByKey(string $id): ?User
    {
        return User::query()->whereKey($id)->first();
    }
}

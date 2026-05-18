<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Busca un usuario por su clave primaria (id).
     */
    public function findByKey(string $id): ?User
    {
        return User::query()->whereKey($id)->first();
    }
}

<?php

declare(strict_types=1);

namespace App\Dtos;

use App\Models\User;

final readonly class UserRefDto
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function fromModel(User $m): self
    {
        return new self(id: $m->id, name: $m->name);
    }
}

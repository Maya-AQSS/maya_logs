<?php

declare(strict_types=1);

namespace App\Dtos;

use App\Models\Application;

final readonly class ApplicationRefDto
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    public static function fromModel(Application $m): self
    {
        return new self(id: $m->id, name: $m->name);
    }
}

<?php

declare(strict_types=1);

namespace App\Dtos;

use App\Models\ErrorCode;

final readonly class ErrorCodeRefDto
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
    ) {}

    public static function fromModel(ErrorCode $m): self
    {
        return new self(id: $m->id, code: $m->code, name: $m->name);
    }
}

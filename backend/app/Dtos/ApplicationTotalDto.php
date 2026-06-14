<?php

declare(strict_types=1);

namespace App\Dtos;

final readonly class ApplicationTotalDto
{
    public function __construct(
        public int $applicationId,
        public string $name,
        public int $total,
    ) {}

    /**
     * @param  array{application_id: int, name: string, total: int}  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            applicationId: (int) $row['application_id'],
            name: (string) $row['name'],
            total: (int) $row['total'],
        );
    }

    /**
     * @return array{application_id: int, name: string, total: int}
     */
    public function toArray(): array
    {
        return [
            'application_id' => $this->applicationId,
            'name' => $this->name,
            'total' => $this->total,
        ];
    }
}

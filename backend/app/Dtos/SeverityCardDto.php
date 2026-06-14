<?php

declare(strict_types=1);

namespace App\Dtos;

final readonly class SeverityCardDto
{
    public function __construct(
        public string $key,
        public int $totalCount,
        public int $resolvedCount,
        public int $unresolvedCount,
    ) {}

    /**
     * @param  array{key: string, totalCount: int, resolvedCount: int, unresolvedCount: int}  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            key: (string) $row['key'],
            totalCount: (int) $row['totalCount'],
            resolvedCount: (int) $row['resolvedCount'],
            unresolvedCount: (int) $row['unresolvedCount'],
        );
    }

    /**
     * @return array{key: string, totalCount: int, resolvedCount: int, unresolvedCount: int}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'totalCount' => $this->totalCount,
            'resolvedCount' => $this->resolvedCount,
            'unresolvedCount' => $this->unresolvedCount,
        ];
    }
}

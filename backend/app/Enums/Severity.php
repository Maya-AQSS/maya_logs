<?php

declare(strict_types=1);

namespace App\Enums;

enum Severity: string
{
    case CRITICAL = 'critical';
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';
    case OTHER = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $severity): string => $severity->value,
            self::cases()
        );
    }

    public static function validationRule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}

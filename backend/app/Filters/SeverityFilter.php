<?php

declare(strict_types=1);

namespace App\Filters;

use App\Enums\Severity;

class SeverityFilter
{
    /**
     * Normaliza la entrada del filtro de severidad.
     *
     * - `null`/`''` => []
     * - `string` => [string]
     * - `array` => array de strings
     *
     * Si hay valores fuera del enum, responde con 422.
     *
     * @param  array<int,string>|string|null  $value
     * @return array<int,string>
     */
    public static function normalize(array|string|null $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        $values = is_array($value) ? $value : [$value];

        $values = array_values(array_filter(
            $values,
            static fn ($v): bool => is_string($v) && $v !== ''
        ));

        $allowed = Severity::values();
        $invalid = array_diff($values, $allowed);

        if ($invalid !== []) {
            abort(422);
        }

        return array_values(array_unique($values));
    }
}

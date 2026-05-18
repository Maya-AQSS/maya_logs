<?php

declare(strict_types=1);

namespace App\Support;

class LikeEscaper
{
    public const LIKE_ESCAPE_CHARACTER = '!';

    public static function escapeLikePattern(string $value): string
    {
        // Escapar primero el carácter de escape, luego los comodines
        $value = str_replace(self::LIKE_ESCAPE_CHARACTER, self::LIKE_ESCAPE_CHARACTER.self::LIKE_ESCAPE_CHARACTER, $value);
        $value = str_replace('%', self::LIKE_ESCAPE_CHARACTER.'%', $value);
        $value = str_replace('_', self::LIKE_ESCAPE_CHARACTER.'_', $value);

        return $value;
    }
}

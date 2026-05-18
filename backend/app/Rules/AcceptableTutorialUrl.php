<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Acepta URLs http/https con host "usable": dominio con punto, localhost, IPv4 o IPv6.
 *
 * filter_var acepta hosts de una sola etiqueta (p. ej. https://example).
 * Esta rule añade la restricción adicional sobre el host.
 */
class AcceptableTutorialUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $url = trim((string) $value);

        if ($url === '') {
            return;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $fail(__('archived_logs.validation.url_tutorial'));

            return;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail(__('archived_logs.validation.url_tutorial'));

            return;
        }

        $host = (string) ($parts['host'] ?? '');

        if ($host === '') {
            $fail(__('archived_logs.validation.url_tutorial'));

            return;
        }

        $hostLower = strtolower($host);

        if ($hostLower === 'localhost') {
            return;
        }

        if (preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $host) === 1) {
            return;
        }

        if (str_starts_with($host, '[') && str_contains($host, ':')) {
            return;
        }

        if (! str_contains($host, '.')) {
            $fail(__('archived_logs.validation.url_tutorial'));
        }
    }
}

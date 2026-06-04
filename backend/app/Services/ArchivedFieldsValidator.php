<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Domain value object for ArchivedLog field validation.
 *
 * Encapsulates the whitelist of fields that can be updated on archived logs,
 * providing validation and sanitization services.
 */
final class ArchivedFieldsValidator
{
    /**
     * Allowed fields that can be updated on archived logs.
     *
     * @var array<int, string>
     */
    private const ALLOWED_FIELDS = [
        'resolved',
        'error_code_id',
        'internal_notes',
        'description',
        'url_tutorial',
    ];

    /**
     * Filter a fields array to only include allowed fields.
     *
     * @param  array<string, mixed>  $fields  The fields to filter
     * @return array<string, mixed> Only the allowed fields
     */
    public function filterAllowed(array $fields): array
    {
        return array_intersect_key($fields, array_flip(self::ALLOWED_FIELDS));
    }

    /**
     * Check if a field is allowed to be updated.
     *
     * @param  string  $field  The field name to check
     * @return bool true if the field is in the whitelist
     */
    public function isAllowed(string $field): bool
    {
        return in_array($field, self::ALLOWED_FIELDS, true);
    }

    /**
     * Get the list of allowed fields.
     *
     * @return array<int, string>
     */
    public function getAllowedFields(): array
    {
        return self::ALLOWED_FIELDS;
    }
}

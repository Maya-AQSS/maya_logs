<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Dtos\LogFilterDto;
use Maya\Http\Http\Requests\PaginatedFilterRequest;

class ListLogsRequest extends PaginatedFilterRequest
{
    protected function filterRules(): array
    {
        return [
            'severity' => ['nullable', 'string'],  // string CSV — se parsea en getParsedSeverity()
            'app_slug' => ['nullable', 'string', 'max:100'],
            'application_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'error_code' => ['nullable', 'string', 'max:50'],
            'archived' => ['nullable', 'string', 'in:only,without'],
            'resolved' => ['nullable', 'string', 'in:only,unresolved'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string', 'in:created_at,severity,application'],
        ];
    }

    /**
     * Parsea el campo severity, que puede llegar como string CSV o como array.
     *
     * @return list<string>|null
     */
    public function getParsedSeverity(): ?array
    {
        $severity = $this->input('severity');

        if ($severity === null) {
            return null;
        }

        if (is_array($severity)) {
            $values = array_values(array_filter(array_map('trim', $severity), fn (string $v): bool => $v !== ''));

            return $values !== [] ? $values : null;
        }

        $values = array_values(array_filter(array_map('trim', explode(',', (string) $severity)), fn (string $v): bool => $v !== ''));

        return $values !== [] ? $values : null;
    }

    public function toFilterDto(): LogFilterDto
    {
        return LogFilterDto::fromRequest($this);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Maya\Http\Filters\DateRangeFilter;

class ListArchivedLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth delegada al middleware JWT de la ruta /api/v1.
        return true;
    }

    public function rules(): array
    {
        return [
            'severity' => ['nullable'], // string CSV o array — se normaliza en el controller
            'application_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'sort_by' => ['nullable', 'string', 'in:archived_at,original_created_at,severity,application'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function passedValidation(): void
    {
        [$from, $to] = DateRangeFilter::normalize(
            $this->input('date_from'),
            $this->input('date_to'),
            'date_from',
            'date_to',
        );

        $this->merge(array_filter([
            'date_from' => $from,
            'date_to' => $to,
        ], fn ($v) => $v !== null));
    }

    /**
     * Parsea el campo severity, que puede llegar como string CSV o como array,
     * de forma idéntica a {@see ListLogsRequest::getParsedSeverity()}.
     *
     * @return list<string>|null
     */
    public function getParsedSeverity(): ?array
    {
        $severity = $this->validated('severity');

        if ($severity === null) {
            return null;
        }

        if (is_array($severity)) {
            $values = array_values(array_filter(array_map('trim', $severity), fn (string $v): bool => $v !== ''));

            return $values !== [] ? $values : null;
        }

        $values = array_values(array_filter(
            array_map('trim', explode(',', (string) $severity)),
            fn (string $v): bool => $v !== '',
        ));

        return $values !== [] ? $values : null;
    }

    public function getApplicationId(): ?int
    {
        $applicationId = $this->validated('application_id');

        return $applicationId !== null ? (int) $applicationId : null;
    }

    public function getDateFrom(): ?string
    {
        $value = $this->validated('date_from');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function getDateTo(): ?string
    {
        $value = $this->validated('date_to');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function getSortBy(): ?string
    {
        $value = $this->validated('sort_by');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function getSortDir(): string
    {
        $value = $this->validated('sort_dir');

        return is_string($value) && $value !== '' ? $value : 'desc';
    }

    public function getPerPage(): int
    {
        $perPage = (int) ($this->validated('per_page') ?? 15);

        return $perPage > 0 ? $perPage : 15;
    }
}

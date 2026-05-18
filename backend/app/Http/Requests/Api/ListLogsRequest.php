<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Maya\Http\Filters\DateRangeFilter;

class ListLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth delegada al middleware JWT de la ruta /api/v1.
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'severity' => ['nullable'], // string CSV o array — se normaliza en el controller
            'application_id' => ['nullable', 'integer', 'min:1'],
            'archived' => ['nullable', 'string', 'in:only,without'],
            'resolved' => ['nullable', 'string', 'in:only,unresolved'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'sort_by' => ['nullable', 'string', 'in:created_at,severity,application,resolved'],
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
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Maya\Http\Http\Requests\PaginatedFilterRequest;

class ListErrorCodesRequest extends PaginatedFilterRequest
{
    protected function filterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'application_id' => ['nullable', 'integer', 'exists:applications,id'],
            'sort_by' => ['nullable', 'string', 'in:code,application,name,file,line'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}

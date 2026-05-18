<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreErrorCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'application_id' => ['required', 'integer', 'exists:applications,id'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('error_codes')->where('application_id', $this->input('application_id')),
            ],
            'name' => ['required', 'string', 'max:200'],
            'file' => ['nullable', 'string', 'max:255'],
            'line' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'application_id.required' => __('error_codes.validation.application_id_required'),
            'application_id.exists' => __('error_codes.validation.application_id_invalid'),
            'code.required' => __('error_codes.validation.code_required'),
            'code.max' => __('error_codes.validation.code_max'),
            'code.unique' => __('error_codes.validation.code_unique'),
            'name.required' => __('error_codes.validation.name_required'),
            'name.max' => __('error_codes.validation.name_max'),
            'description.max' => __('error_codes.validation.description_max'),
            'file.max' => __('error_codes.validation.file_max'),
            'line.integer' => __('error_codes.validation.line_integer'),
            'line.min' => __('error_codes.validation.line_min'),
        ];
    }
}

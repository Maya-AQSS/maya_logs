<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Rules\AcceptableTutorialUrl;
use Illuminate\Foundation\Http\FormRequest;

class UpdateArchivedLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:5000'],
            'url_tutorial' => ['nullable', new AcceptableTutorialUrl(), 'max:2048'],
        ];
    }
}

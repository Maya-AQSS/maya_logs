<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth delegada al middleware JWT de la ruta /api/v1: quién puede comentar
        // lo decide el token; no hay regla de ownership en la creación de un comentario
        // (esa sí aplica en update/delete vía CommentPolicy). Consistente con los
        // List requests (ListArchivedLogsRequest, ListLogsRequest).
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:3'],
        ];
    }
}

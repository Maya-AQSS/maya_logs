<?php

declare(strict_types=1);

namespace App\Policies;

use App\Http\Controllers\Api\LogController;
use App\Models\ArchivedLog;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Http\Request;

/**
 * Mutaciones sobre un log archivado: solo el subject JWT que coincide con
 * `archived_logs.archived_by_id` (mismo criterio que POST /logs/{id}/archive).
 * No consulta la vista FDW `users`.
 */
class ArchivedLogPolicy
{
    public function __construct(
        private readonly Request $request,
    ) {}

    /**
     * Verifica si el usuario puede actualizar el log archivado.
     */
    public function update(?User $user, ArchivedLog $archivedLog): Response
    {
        return $this->archiverResponse($archivedLog);
    }

    /**
     * Verifica si el usuario puede eliminar el log archivado.
     */
    public function delete(?User $user, ArchivedLog $archivedLog): Response
    {
        return $this->archiverResponse($archivedLog);
    }

    /**
     * Verifica si el usuario puede realizar la acción solicitada sobre el log archivado.
     */
    private function archiverResponse(ArchivedLog $archivedLog): Response
    {
        $subject = $this->jwtSubject();
        if ($subject === null) {
            return Response::deny(__('logs.actor_missing'), 'actor_missing')->withStatus(403);
        }

        if ($subject !== (string) $archivedLog->archived_by_id) {
            return Response::deny(__('logs.archived_log_forbidden'), 'archived_log_forbidden')->withStatus(403);
        }

        return Response::allow();
    }

    /**
     * Identificador de actor del token (misma clave que en {@see LogController::archive}).
     */
    private function jwtSubject(): ?string
    {
        /** @var array<string, mixed>|null $jwtUser */
        $jwtUser = $this->request->attributes->get('jwt_user');
        $id = is_array($jwtUser) ? ($jwtUser['id'] ?? null) : null;

        return is_string($id) && $id !== '' ? $id : null;
    }
}

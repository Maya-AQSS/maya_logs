<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Dtos\UserRefDto;
use App\Services\PanelUserService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Maya\Auth\Dtos\JwtProfileDto;

/**
 * Resolución del actor de un comentario: JWT del request → usuario del
 * directorio (DTO). Requiere una propiedad `panelUserService` en la clase.
 *
 * @property-read PanelUserService $panelUserService
 */
trait ResolvesCommentActor
{
    private function resolveActor(Request $request): UserRefDto
    {
        return $this->panelUserService->resolveFromJwtProfile($this->extractJwtProfile($request));
    }

    private function extractJwtProfile(Request $request): JwtProfileDto
    {
        $jwtProfile = JwtProfileDto::fromRequestAttribute($request);

        if ($jwtProfile === null) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'actor_missing',
                    'message' => __('logs.actor_missing'),
                ],
            ], 403));
        }

        return $jwtProfile;
    }
}

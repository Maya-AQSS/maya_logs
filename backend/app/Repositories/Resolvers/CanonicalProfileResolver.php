<?php

declare(strict_types=1);

namespace App\Repositories\Resolvers;

use Maya\Profile\Dtos\UserProfileDto;
use Maya\Profile\Repositories\Contracts\UserProfileResolverInterface;
use Maya\Profile\Repositories\Resolvers\JwtPassthroughResolver;

/**
 * Resolver de perfil canónico para maya_logs.
 *
 * Parte del DTO que produce {@see JwtPassthroughResolver} y normaliza al
 * shape canónico cross-app (snake_case en inglés):
 *
 *   `permissions`, `study_type_ids`, `study_ids`, `module_ids`, `team_ids`.
 *
 * maya_logs NO tiene tablas locales de permisos ni de relaciones académicas
 * (la fuente de verdad de permisos está en `maya_authorization` y las
 * relaciones académicas en `maya_dms`). Por eso este resolver expone los 5
 * campos como **arrays vacíos** — el frontend del SPA puede asumir siempre
 * la forma canónica sin checks adicionales. La autorización dentro de logs
 * se delega al middleware `RequirePermission` (resolver remoto FDW), no al
 * payload de `/me`.
 *
 * Campos eliminados del passthrough JWT: `roles`, `departamento`,
 * `department`, `organizacion_id`, `organization_id` (claims que no deben
 * exponerse en /me).
 */
final class CanonicalProfileResolver implements UserProfileResolverInterface
{
    private const EXTRA_DROP_KEYS = ['roles', 'departamento', 'department', 'organizacion_id', 'organization_id'];

    public function __construct(
        private readonly JwtPassthroughResolver $base = new JwtPassthroughResolver(),
    ) {}

    public function resolve(string $userId, array $jwtProfile): UserProfileDto
    {
        $dto = $this->base->resolve($userId, $jwtProfile);

        $extra = array_diff_key($dto->extra, array_flip(self::EXTRA_DROP_KEYS));
        $extra['permissions'] = [];
        $extra['study_type_ids'] = [];
        $extra['study_ids'] = [];
        $extra['module_ids'] = [];
        $extra['team_ids'] = [];

        return new UserProfileDto(
            id: $dto->id,
            email: $dto->email,
            name: $dto->name,
            locale: $dto->locale,
            extra: $extra,
        );
    }

    public function invalidate(string $userId): void
    {
        $this->base->invalidate($userId);
    }
}

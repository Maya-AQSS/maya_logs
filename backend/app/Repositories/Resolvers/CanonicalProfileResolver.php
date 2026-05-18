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
 * shape canónico cross-app (2026-05-18 — campos en español):
 *
 *   `permisos`, `tipo_estudios`, `estudios`, `modulos`, `equipos`.
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
 * `department`, `organizacion_id` (claims que no deben exponerse en /me).
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
        $extra['permisos'] = [];
        $extra['tipo_estudios'] = [];
        $extra['estudios'] = [];
        $extra['modulos'] = [];
        $extra['equipos'] = [];

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

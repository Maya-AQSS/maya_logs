<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * DTO representing the JWT profile extracted from the jwt_user request attribute.
 * Used at the service boundary to extract DTO/scalar before passing to PanelUserService.
 */
final readonly class JwtProfileDto
{
    public function __construct(
        public string $id,
    ) {}

    /**
     * Extract JWT profile from request attributes.
     *
     * @param array<string, mixed>|null $jwtUser JWT profile from request->attributes->get('jwt_user')
     * @return self|null null if jwt_user is invalid or missing
     */
    public static function fromRequestAttribute(?array $jwtUser): ?self
    {
        if (! is_array($jwtUser)) {
            return null;
        }

        $id = $jwtUser['id'] ?? null;
        if (! is_string($id) || $id === '') {
            return null;
        }

        return new self(id: $id);
    }
}

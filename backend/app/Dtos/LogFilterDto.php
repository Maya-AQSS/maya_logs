<?php

declare(strict_types=1);

namespace App\Dtos;

use App\Http\Requests\Api\ListLogsRequest;
use Maya\Http\Data\FilterDto;
use Maya\Http\Http\Requests\PaginatedFilterRequest;

/**
 * Criterios de filtrado paginado para listados de logs.
 *
 * Extiende FilterDto con los filtros de dominio propios del módulo:
 * severidad, aplicación (por slug), rango de fechas y código de error.
 */
readonly class LogFilterDto extends FilterDto
{
    public function __construct(
        /** @var list<string>|null Severidades a incluir: critical, high, medium, low, other */
        public readonly ?array $severity = null,
        /** Slug de la aplicación (join con applications.slug) */
        public readonly ?string $appSlug = null,
        /** ID de la aplicación (filtro directo sobre logs.application_id) */
        public readonly ?int $applicationId = null,
        /** ISO date — límite inferior de created_at */
        public readonly ?string $from = null,
        /** ISO date — límite superior de created_at */
        public readonly ?string $to = null,
        /** Código de error para filtrar por error_code_id (via join con error_codes.code) */
        public readonly ?string $errorCode = null,
        /** Filtro de archivado: 'only' | 'without' | null */
        public readonly ?string $archived = null,
        /** Filtro de resolución: 'only' | 'unresolved' | null */
        public readonly ?string $resolved = null,
        int $page = 1,
        int $perPage = 50,
        ?string $sortBy = 'created_at',
        string $sortDir = 'desc',
        ?string $search = null,
    ) {
        parent::__construct($page, $perPage, $sortBy, $sortDir, $search);
    }

    public static function fromRequest(PaginatedFilterRequest $request): static
    {
        /** @var ListLogsRequest $request */
        return new static(
            severity: $request->getParsedSeverity(),
            appSlug: $request->input('app_slug') ?: null,
            applicationId: $request->input('application_id') !== null ? (int) $request->input('application_id') : null,
            from: $request->input('date_from') ?: null,
            to: $request->input('date_to') ?: null,
            errorCode: $request->input('error_code') ?: null,
            archived: $request->input('archived') ?: null,
            resolved: $request->input('resolved') ?: null,
            page: $request->getPage(),
            perPage: $request->getPerPage(),
            sortBy: $request->getSortBy() ?? 'created_at',
            sortDir: $request->getSortDir(),
            search: $request->input('search') ?: null,
        );
    }
}

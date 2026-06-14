<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ArchivedLog;

/**
 * Domain service for severity ranking logic.
 *
 * Encapsulates business rules for sorting ArchivedLogs by severity level.
 * Severity hierarchy: critical → high → medium → low → other
 *
 * Esta clase mantiene SOLO la regla de dominio pura (la jerarquía y la validación
 * de dirección). La construcción del fragmento SQL (CASE/ORDER BY) vive en la capa
 * Eloquent — {@see ArchivedLog::scopeOrderBySeverityRank()}.
 */
final class SeverityRankingService
{
    /**
     * Severity levels ordered by priority (highest to lowest).
     *
     * @var array<int, string>
     */
    private const SEVERITY_HIERARCHY = ['critical', 'high', 'medium', 'low', 'other'];

    /**
     * Devuelve la jerarquía de severidad ordenada de mayor a menor prioridad.
     *
     * @return list<string>
     */
    public function severityHierarchy(): array
    {
        return self::SEVERITY_HIERARCHY;
    }

    /**
     * Validate that a sort direction is allowed.
     *
     * @param  string  $direction  The direction to validate
     * @return string The validated direction (defaults to 'asc' if invalid)
     */
    public function validateSortDirection(string $direction): string
    {
        return in_array(strtolower($direction), ['asc', 'desc'], true)
            ? strtolower($direction)
            : 'asc';
    }
}

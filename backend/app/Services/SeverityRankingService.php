<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * Domain service for severity ranking logic.
 *
 * Encapsulates business rules for sorting ArchivedLogs by severity level.
 * Severity hierarchy: critical → high → medium → low → other
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
     * Apply severity ranking to a query builder.
     *
     * Orders results by the business severity hierarchy (critical→high→medium→low→other),
     * then by archived_at and id for stable pagination.
     *
     * @param  string  $direction  'asc' or 'desc' (applies to the rank order)
     * @return Builder with applied severity ordering
     */
    public function applyRankOrder(Builder $query, string $direction): Builder
    {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        // Build CASE statement for severity ranking
        $caseStatement = 'CASE severity ';
        $bindings = [];
        foreach (self::SEVERITY_HIERARCHY as $rank => $severity) {
            $caseStatement .= 'WHEN ? THEN ' . ($rank + 1) . ' ';
            $bindings[] = $severity;
        }
        $caseStatement .= 'ELSE ' . (count(self::SEVERITY_HIERARCHY) + 1) . ' END ' . $dir;

        return $query
            ->orderByRaw($caseStatement, $bindings)
            ->orderByDesc('archived_at')
            ->orderByDesc('id');
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

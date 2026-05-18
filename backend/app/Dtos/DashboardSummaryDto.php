<?php

declare(strict_types=1);

namespace App\Dtos;

final readonly class DashboardSummaryDto
{
    /**
     * @param  array<int, array{key: string, totalCount: int, resolvedCount: int, unresolvedCount: int}>  $severityCards
     * @param  array<int, array{application_id: int, name: string, total: int}>  $applicationTotals
     */
    public function __construct(
        public array $severityCards,
        public array $applicationTotals,
    ) {}
}

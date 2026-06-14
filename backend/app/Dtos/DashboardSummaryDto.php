<?php

declare(strict_types=1);

namespace App\Dtos;

final readonly class DashboardSummaryDto
{
    /**
     * @param  list<SeverityCardDto>  $severityCards
     * @param  list<ApplicationTotalDto>  $applicationTotals
     */
    public function __construct(
        public array $severityCards,
        public array $applicationTotals,
    ) {}

    /**
     * Construye el DTO a partir de los agregados crudos que produce el LogService.
     *
     * @param  array<int, array{key: string, totalCount: int, resolvedCount: int, unresolvedCount: int}>  $severityCards
     * @param  array<int, array{application_id: int, name: string, total: int}>  $applicationTotals
     */
    public static function fromAggregates(array $severityCards, array $applicationTotals): self
    {
        return new self(
            severityCards: array_values(array_map(
                static fn (array $row): SeverityCardDto => SeverityCardDto::fromArray($row),
                $severityCards,
            )),
            applicationTotals: array_values(array_map(
                static fn (array $row): ApplicationTotalDto => ApplicationTotalDto::fromArray($row),
                $applicationTotals,
            )),
        );
    }
}

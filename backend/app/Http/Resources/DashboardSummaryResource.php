<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Dtos\ApplicationTotalDto;
use App\Dtos\DashboardSummaryDto;
use App\Dtos\SeverityCardDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var DashboardSummaryDto $dto */
        $dto = $this->resource;

        return [
            'severity_cards' => array_map(
                static fn (SeverityCardDto $card): array => $card->toArray(),
                $dto->severityCards,
            ),
            'application_totals' => array_map(
                static fn (ApplicationTotalDto $total): array => $total->toArray(),
                $dto->applicationTotals,
            ),
        ];
    }
}

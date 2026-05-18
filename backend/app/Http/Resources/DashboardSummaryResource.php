<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Dtos\DashboardSummaryDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var DashboardSummaryDto $dto */
        $dto = $this->resource;

        return [
            'severity_cards' => $dto->severityCards,
            'application_totals' => $dto->applicationTotals,
        ];
    }
}

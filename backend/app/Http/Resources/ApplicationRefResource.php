<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Dtos\ApplicationRefDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ApplicationRefDto $resource
 */
class ApplicationRefResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ApplicationRefDto $dto */
        $dto = $this->resource;

        return [
            'id' => $dto->id,
            'name' => $dto->name,
        ];
    }
}

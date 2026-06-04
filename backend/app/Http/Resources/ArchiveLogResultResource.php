<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{archived_log_id: int, already_archived: bool} $resource
 */
class ArchiveLogResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'archived_log_id' => $this->resource['archived_log_id'],
            'already_archived' => $this->resource['already_archived'],
        ];
    }
}

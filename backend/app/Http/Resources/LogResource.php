<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Dtos\LogDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property LogDto $resource
 */
class LogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LogDto $dto */
        $dto = $this->resource;

        $payload = [
            'id' => $dto->id,
            'severity' => $dto->severity,
            'message' => $dto->message,
            'metadata' => $dto->metadata,
            'resolved' => $dto->resolved,
            'file' => $dto->file,
            'line' => $dto->line,
            'created_at' => $dto->createdAt,
        ];

        if ($dto->applicationLoaded && $dto->application !== null) {
            $payload['application'] = [
                'id' => $dto->application->id,
                'name' => $dto->application->name,
            ];
        }

        if ($dto->errorCodeLoaded) {
            $payload['error_code'] = $dto->errorCode !== null
                ? [
                    'id' => $dto->errorCode->id,
                    'code' => $dto->errorCode->code,
                    'name' => $dto->errorCode->name,
                ]
                : null;
        }

        return $payload;
    }
}

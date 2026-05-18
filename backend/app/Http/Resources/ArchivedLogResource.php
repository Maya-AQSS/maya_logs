<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Dtos\ArchivedLogDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ArchivedLogDto $resource
 */
class ArchivedLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ArchivedLogDto $dto */
        $dto = $this->resource;

        $payload = [
            'id' => $dto->id,
            'severity' => $dto->severity,
            'message' => $dto->message,
            'metadata' => $dto->metadata,
            'metadata_formatted' => $dto->metadataFormatted,
            'description' => $dto->description,
            'url_tutorial' => $dto->urlTutorial,
            'original_created_at' => $dto->originalCreatedAt,
            'archived_at' => $dto->archivedAt,
            'updated_at' => $dto->updatedAt,
            'deleted_at' => $dto->deletedAt,
        ];

        if ($dto->applicationLoaded) {
            $payload['application'] = $dto->application !== null
                ? ['id' => $dto->application->id, 'name' => $dto->application->name]
                : null;
        }

        if ($dto->archivedByLoaded) {
            $payload['archived_by'] = $dto->archivedBy !== null
                ? ['id' => $dto->archivedBy->id, 'name' => $dto->archivedBy->name]
                : null;
        }

        if ($dto->errorCodeLoaded) {
            $payload['error_code'] = $dto->errorCode !== null
                ? ['id' => $dto->errorCode->id, 'code' => $dto->errorCode->code, 'name' => $dto->errorCode->name]
                : null;
        }

        if ($dto->commentsCount !== null) {
            $payload['comments_count'] = $dto->commentsCount;
        }

        return $payload;
    }
}

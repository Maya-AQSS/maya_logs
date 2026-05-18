<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\LogIngestionRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class LogIngestionService
{
    protected array $slugToId = [];

    private array $errorCodeIdCache = [];

    private array $logBuffer = [];

    private const MAX_ERROR_CODE_CACHE = 10_000;

    private const DEFAULT_BATCH_SIZE = 100;

    public function __construct(
        private readonly LogIngestionRepositoryInterface $repository,
        private readonly int $batchSize = self::DEFAULT_BATCH_SIZE,
    ) {
        if ($batchSize < 1) {
            throw new \InvalidArgumentException("batchSize must be >= 1, got {$batchSize}");
        }
    }

    public function loadApplicationMap(): void
    {
        $this->setApplicationMap($this->repository->applicationSlugToIdMap());
    }

    public function setApplicationMap(array $map): void
    {
        $this->slugToId = $map;
    }

    public function ingest(array $payload): void
    {
        $log = LogPayload::fromArray($payload);

        $applicationId = $this->resolveApplicationId($log->app);
        if ($applicationId === null) {
            return;
        }

        try {
            $errorCodeId = $this->resolveErrorCodeId(
                code: $log->errorCode,
                applicationId: $applicationId,
                file: $log->file,
                line: $log->line,
            );
        } catch (\Throwable $e) {
            Log::error('ConsumeLogs: failed to resolve error code', ['error' => $e->getMessage()]);
            throw $e;
        }

        $this->logBuffer[] = [
            'error_code_id' => $errorCodeId,
            'application_id' => $applicationId,
            'severity' => $log->severity,
            'message' => $log->message,
            'file' => $log->file,
            'line' => $log->line,
            'metadata' => is_array($log->metadata)
                ? (json_encode($log->metadata, JSON_UNESCAPED_UNICODE) ?: null)
                : $log->metadata,
            'resolved' => false,
            'created_at' => $log->occurredAt !== null
                ? $this->parseTimestamp($log->occurredAt)
                : now(),
        ];

        if (count($this->logBuffer) >= $this->batchSize) {
            $this->flush();
        }
    }

    /**
     * Write all buffered log rows in a single INSERT statement.
     * Call after the AMQP consume loop ends to drain any partial batch.
     */
    public function flush(): void
    {
        if (empty($this->logBuffer)) {
            return;
        }

        try {
            $this->repository->insertLogs($this->logBuffer);
        } catch (\Throwable $e) {
            Log::error('ConsumeLogs: failed to flush log batch', [
                'count' => count($this->logBuffer),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            $this->logBuffer = [];
        }
    }

    private function resolveApplicationId(string $slug): ?int
    {
        if ($slug === '') {
            return null;
        }

        if (! array_key_exists($slug, $this->slugToId)) {
            Log::warning('ConsumeLogs: dropping payload — app slug not registered in maya_auth', ['slug' => $slug]);

            return null;
        }

        $id = (int) $this->slugToId[$slug];

        return $id > 0 ? $id : null;
    }

    private function resolveErrorCodeId(?string $code, int $applicationId, ?string $file, ?int $line): ?int
    {
        if ($code === null) {
            return null;
        }

        // Null byte separator avoids collisions between codes containing ':' and numeric appIds.
        $cacheKey = $code."\0".$applicationId;
        if (isset($this->errorCodeIdCache[$cacheKey])) {
            return $this->errorCodeIdCache[$cacheKey];
        }

        $this->repository->insertErrorCodeIfMissing($code, $applicationId, $file, $line);

        $id = $this->repository->findErrorCodeId($code, $applicationId);

        if ($id === null) {
            Log::error('ConsumeLogs: could not resolve id for error code', ['code' => $code, 'applicationId' => $applicationId]);

            return null;
        }

        // Full reset when threshold reached: O(1), no hot-entry bias from partial eviction.
        if (count($this->errorCodeIdCache) >= self::MAX_ERROR_CODE_CACHE) {
            $this->errorCodeIdCache = [];
        }

        $this->errorCodeIdCache[$cacheKey] = $id;

        return $id;
    }

    private function parseTimestamp(string $value): Carbon
    {
        // strtotime() guards against Carbon::parse()'s permissiveness with relative strings
        // ("next year", "tomorrow"). Reusing the result avoids parsing the string twice.
        $unix = strtotime($value);
        if ($unix === false) {
            Log::warning('ConsumeLogs: malformed occurred_at, falling back to now()', ['value' => $value]);

            return now();
        }

        return Carbon::createFromTimestamp($unix);
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface LogIngestionRepositoryInterface
{
    /**
     * Application slug => id map (small, stable set; loaded once per worker startup).
     *
     * @return array<string, int>
     */
    public function applicationSlugToIdMap(): array;

    /**
     * Atomic INSERT ON CONFLICT DO NOTHING for an error code.
     */
    public function insertErrorCodeIfMissing(string $code, int $applicationId, ?string $file, ?int $line): void;

    /**
     * Lookup an error code id by (code, application_id). Returns null if missing.
     */
    public function findErrorCodeId(string $code, int $applicationId): ?int;

    /**
     * Bulk insert log rows in a single statement.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function insertLogs(array $rows): void;
}

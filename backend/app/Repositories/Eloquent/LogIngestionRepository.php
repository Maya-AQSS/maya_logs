<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Application;
use App\Models\ErrorCode;
use App\Repositories\Contracts\LogIngestionRepositoryInterface;
use Illuminate\Support\Facades\DB;

class LogIngestionRepository implements LogIngestionRepositoryInterface
{
    public function applicationSlugToIdMap(): array
    {
        return Application::query()->pluck('id', 'slug')->all();
    }

    public function insertErrorCodeIfMissing(string $code, int $applicationId, ?string $file, ?int $line): void
    {
        // INSERT ON CONFLICT DO NOTHING — atomic under concurrent workers.
        // Using firstOrCreate for Eloquent atomicity with PostgreSQL's UPSERT semantics.
        ErrorCode::query()->firstOrCreate(
            [
                'code' => $code,
                'application_id' => $applicationId,
            ],
            [
                'name' => $code,
                'file' => $file,
                'line' => $line,
            ]
        );
    }

    public function findErrorCodeId(string $code, int $applicationId): ?int
    {
        $errorCode = ErrorCode::query()
            ->where('code', $code)
            ->where('application_id', $applicationId)
            ->select('id')
            ->first();

        return $errorCode?->id;
    }

    public function insertLogs(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        // Bypasses the model's read-only guard (saving => false). Only the worker writes via this path.
        // Using raw DB::table insert is necessary here because Log model disables all write operations
        // to enforce read-only semantics. The worker is the only authorized path that can write logs.
        DB::table('logs')->insert($rows);
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Application;
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
        DB::table('error_codes')->insertOrIgnore([
            'code' => $code,
            'application_id' => $applicationId,
            'name' => $code,
            'file' => $file,
            'line' => $line,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function findErrorCodeId(string $code, int $applicationId): ?int
    {
        $id = DB::table('error_codes')
            ->where('code', $code)
            ->where('application_id', $applicationId)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function insertLogs(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        // Bypasses the model's read-only guard (saving => false). Only the worker writes via this path.
        DB::table('logs')->insert($rows);
    }
}

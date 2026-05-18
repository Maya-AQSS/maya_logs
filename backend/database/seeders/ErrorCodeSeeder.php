<?php

namespace Database\Seeders;

use App\Models\ErrorCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ErrorCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * 'applications' es una vista FDW de solo lectura sobre maya_auth.applications.
     * Resolvemos por slug si viene 'application' en el mock; nunca creamos apps.
     */
    public function run(): void
    {
        $errorCodes = require database_path('data/mock-error-codes.php');

        $appIdBySlug = DB::table('applications')->pluck('id', 'slug')->toArray();

        foreach ($errorCodes as $errorCode) {
            $appId = $errorCode['application_id'] ?? null;

            if ($appId === null && isset($errorCode['application'])) {
                $appId = $appIdBySlug[$errorCode['application']] ?? null;
            }

            if ($appId === null) {
                continue;
            }

            $attributes = [
                'code' => $errorCode['code'],
                'application_id' => $appId,
                'name' => $errorCode['name'],
                'description' => $errorCode['description'] ?? null,
                'file' => $errorCode['file'] ?? null,
                'line' => $errorCode['line'] ?? null,
            ];

            ErrorCode::updateOrCreate(
                [
                    'code' => $errorCode['code'],
                    'application_id' => $appId,
                ],
                $attributes
            );
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('error_codes', 'id'), (SELECT COALESCE(MAX(id), 1) FROM error_codes))"
            );
        }
    }
}

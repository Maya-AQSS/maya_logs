<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * users/applications son vistas FDW read-only — omitir UserSeeder/ApplicationSeeder.
     * TRUNCATE garantiza error_codes.id desde 1 (LogSeeder asume range 1..22).
     */
    public function run(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('TRUNCATE TABLE comments, logs, archived_logs, error_codes RESTART IDENTITY CASCADE');
        }

        $this->call([
            ErrorCodeSeeder::class,
            LogSeeder::class,
            ArchivedLogSeeder::class,
            CommentSeeder::class,
        ]);
    }
}

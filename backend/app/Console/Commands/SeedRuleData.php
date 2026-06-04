<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * QA harness (non-production): inserts enough recent critical logs to trip the
 * `logs.error_spike` rule, so `notifications:evaluate-rules` publishes a real
 * notification through the FDW → evaluator → publish path.
 */
class SeedRuleData extends Command
{
    protected $signature = 'notifications:seed-rule-data {--count=15 : Critical logs to insert}';

    protected $description = 'Siembra datos para disparar las reglas programadas (solo no-producción)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('No disponible en producción.');

            return self::FAILURE;
        }

        $count = (int) $this->option('count');
        $now = now();

        for ($i = 0; $i < $count; $i++) {
            DB::table('logs')->insert([
                'application_id' => 1,
                'severity' => 'critical',
                'message' => 'QA error-spike seed #'.$i,
                'resolved' => false,
                'created_at' => $now,
            ]);
        }

        $this->info("Insertados {$count} logs críticos. Ejecuta: php artisan notifications:evaluate-rules");

        return self::SUCCESS;
    }
}

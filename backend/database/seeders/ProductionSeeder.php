<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ErrorCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de referencia de producción (plan de las apps, § 4.5): solo el catálogo de códigos
 * de error (database/data/mock-error-codes.php).
 *
 * Se lanza a mano y una vez, tras el primer despliegue y después del ProductionSeeder de
 * maya-authorization (las aplicaciones se resuelven por slug en `applications`, que es la
 * tabla de maya_auth por FDW), con el Job de deploy/jobs/artisan-job.yaml:
 *
 *   php artisan db:seed --class=ProductionSeeder --force
 *
 * Nunca el DatabaseSeeder: hace TRUNCATE de logs, archived_logs, comments y error_codes.
 *
 * Idempotente y sin pisar lo editado en TraCEED: crea los códigos que faltan y, de los que
 * ya existen, solo completa los que creó la ingesta sin catálogo (nombre = código y sin
 * descripción). Los códigos de aplicaciones que aún no existen se saltan con un aviso.
 */
final class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = require database_path('data/mock-error-codes.php');
        $appIdBySlug = DB::table('applications')->pluck('id', 'slug')->all();

        $created = $completed = 0;
        $missingApps = [];

        DB::transaction(function () use ($catalog, $appIdBySlug, &$created, &$completed, &$missingApps): void {
            foreach ($catalog as $entry) {
                $slug = (string) ($entry['application'] ?? '');
                $appId = $appIdBySlug[$slug] ?? null;
                if ($appId === null) {
                    $missingApps[$slug] = true;

                    continue;
                }

                $errorCode = ErrorCode::query()->firstOrNew([
                    'code' => $entry['code'],
                    'application_id' => (int) $appId,
                ]);

                $isPlaceholder = $errorCode->exists
                    && $errorCode->name === $errorCode->code
                    && ($errorCode->description === null || $errorCode->description === '');

                if ($errorCode->exists && ! $isPlaceholder) {
                    continue;
                }

                $errorCode->name = $entry['name'];
                $errorCode->description = $entry['description'] ?? null;
                if (! $errorCode->exists) {
                    $errorCode->file = $entry['file'] ?? null;
                    $errorCode->line = $entry['line'] ?? null;
                }

                $errorCode->exists ? $completed++ : $created++;
                $errorCode->save();
            }
        });

        $this->command?->info('  Códigos de error: '.count($catalog)." en el catálogo, {$created} nuevos, {$completed} completados");
        foreach (array_keys($missingApps) as $slug) {
            $this->command?->warn("  ⊘ aplicación «{$slug}» no está en applications: se omiten sus códigos (lanza antes el ProductionSeeder de maya-authorization)");
        }
    }
}

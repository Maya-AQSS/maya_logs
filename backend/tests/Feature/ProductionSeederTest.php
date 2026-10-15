<?php

declare(strict_types=1);

use App\Models\ErrorCode;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Aplicaciones con ids que no siguen el orden del catálogo de desarrollo. */
function seedProductionApplications(array $slugs = ['maya-dashboard', 'maya-dms', 'maya-authorization', 'maya-logs']): void
{
    foreach ($slugs as $i => $slug) {
        DB::table('applications')->insert(['id' => 40 + $i, 'name' => $slug, 'slug' => $slug, 'is_active' => true]);
    }
}

/** @return list<array<string, mixed>> */
function errorCodeCatalog(): array
{
    return require database_path('data/mock-error-codes.php');
}

it('crea el catálogo de códigos resolviendo las aplicaciones por slug', function () {
    seedProductionApplications();

    $this->seed(ProductionSeeder::class);

    expect(ErrorCode::query()->count())->toBe(count(errorCodeCatalog()));

    foreach (['LAR-DASH-001', 'LAR-LOG-001', 'LAR-AUTH-010'] as $code) {
        $slug = collect(errorCodeCatalog())->firstWhere('code', $code)['application'];
        $appId = DB::table('applications')->where('slug', $slug)->value('id');
        expect(ErrorCode::query()->where('code', $code)->value('application_id'))->toBe($appId);
    }
});

it('es idempotente', function () {
    seedProductionApplications();
    $this->seed(ProductionSeeder::class);
    $first = ErrorCode::query()->orderBy('id')->get(['id', 'code', 'application_id', 'name', 'description'])->toArray();

    $this->seed(ProductionSeeder::class);

    expect(ErrorCode::query()->orderBy('id')->get(['id', 'code', 'application_id', 'name', 'description'])->toArray())
        ->toBe($first);
});

it('no borra nada (sin TRUNCATE) ni pisa lo editado en TraCEED', function () {
    seedProductionApplications();
    $this->seed(ProductionSeeder::class);

    $code = ErrorCode::query()->where('code', 'LAR-DB-001')->firstOrFail();
    $code->update(['name' => 'Sin conexión con PostgreSQL', 'description' => 'Texto del centro']);
    DB::table('logs')->insert([
        'application_id' => $code->application_id,
        'error_code_id' => $code->id,
        'severity' => 'high',
        'message' => 'log real',
        'resolved' => false,
        'created_at' => now(),
    ]);

    $this->seed(ProductionSeeder::class);

    expect($code->fresh()->name)->toBe('Sin conexión con PostgreSQL')
        ->and($code->fresh()->description)->toBe('Texto del centro')
        ->and(DB::table('logs')->count())->toBe(1);
});

it('completa los códigos que creó la ingesta sin catálogo', function () {
    seedProductionApplications();
    $logs = DB::table('applications')->where('slug', 'maya-logs')->value('id');
    $code = ErrorCode::query()->create(['code' => 'LAR-LOG-001', 'application_id' => $logs, 'name' => 'LAR-LOG-001']);

    $this->seed(ProductionSeeder::class);

    $entry = collect(errorCodeCatalog())->firstWhere('code', 'LAR-LOG-001');
    expect($code->fresh()->name)->toBe($entry['name'])
        ->and(ErrorCode::query()->where('code', 'LAR-LOG-001')->count())->toBe(1);
});

it('salta los códigos de aplicaciones que aún no existen', function () {
    seedProductionApplications(['maya-logs']);

    $this->seed(ProductionSeeder::class);

    $expected = collect(errorCodeCatalog())->where('application', 'maya-logs')->count();
    expect(ErrorCode::query()->count())->toBe($expected);
});

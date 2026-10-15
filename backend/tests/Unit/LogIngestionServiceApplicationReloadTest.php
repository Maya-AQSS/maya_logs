<?php

declare(strict_types=1);

use App\Repositories\Contracts\LogIngestionRepositoryInterface;
use App\Services\LogIngestionService;
use Illuminate\Support\Carbon;
use Maya\Messaging\Exceptions\UnrecoverableIngestionException;
use Tests\TestCase;

uses(TestCase::class);

/**
 * C6 (plan de plataforma): el worker carga la lista de aplicaciones al arrancar
 * (ConsumeLogs::handle). Una aplicación dada de alta después (p. ej. `odoo`) se reconoce sin
 * reiniciar el worker: al llegar un slug desconocido se recarga la lista, como mucho una vez
 * por minuto (una avalancha de slugs basura no martillea maya_auth).
 */
function fakeIngestionRepository(array $map): LogIngestionRepositoryInterface
{
    return new class($map) implements LogIngestionRepositoryInterface
    {
        public int $mapLoads = 0;

        /** @var list<array<string, mixed>> */
        public array $rows = [];

        public function __construct(public array $map) {}

        public function applicationSlugToIdMap(): array
        {
            $this->mapLoads++;

            return $this->map;
        }

        public function insertErrorCodeIfMissing(string $code, int $applicationId, ?string $file, ?int $line): void {}

        public function findErrorCodeId(string $code, int $applicationId): ?int
        {
            return null;
        }

        public function insertLogs(array $rows): void
        {
            array_push($this->rows, ...$rows);
        }
    };
}

function odooLog(): array
{
    return ['app' => 'odoo', 'severity' => 'high', 'message' => 'Fallo en Odoo'];
}

beforeEach(function () {
    Carbon::setTestNow('2026-10-15 08:00:00');
    $this->repo = fakeIngestionRepository(['maya-logs' => 4]);
    $this->service = new LogIngestionService($this->repo);
    $this->service->loadApplicationMap();
});

afterEach(fn () => Carbon::setTestNow());

it('recarga la lista al llegar un slug desconocido y acepta el log', function () {
    $this->repo->map['odoo'] = 6;
    Carbon::setTestNow('2026-10-15 08:01:00');

    $this->service->ingest(odooLog());

    expect($this->repo->mapLoads)->toBe(2)
        ->and($this->repo->rows)->toHaveCount(1)
        ->and($this->repo->rows[0]['application_id'])->toBe(6);
});

it('no recarga si la lista se cargó hace menos de un minuto', function () {
    $this->repo->map['odoo'] = 6;
    Carbon::setTestNow('2026-10-15 08:00:59');

    expect(fn () => $this->service->ingest(odooLog()))->toThrow(UnrecoverableIngestionException::class);
    expect($this->repo->mapLoads)->toBe(1);
});

it('recarga como mucho una vez por minuto aunque sigan llegando slugs desconocidos', function () {
    Carbon::setTestNow('2026-10-15 08:01:00');
    expect(fn () => $this->service->ingest(['app' => 'ghost', 'severity' => 'low', 'message' => 'x']))
        ->toThrow(UnrecoverableIngestionException::class);

    Carbon::setTestNow('2026-10-15 08:01:30');
    expect(fn () => $this->service->ingest(['app' => 'ghost', 'severity' => 'low', 'message' => 'x']))
        ->toThrow(UnrecoverableIngestionException::class);
    expect(fn () => $this->service->ingest(['app' => 'otro', 'severity' => 'low', 'message' => 'x']))
        ->toThrow(UnrecoverableIngestionException::class);

    expect($this->repo->mapLoads)->toBe(2);

    $this->repo->map['odoo'] = 6;
    Carbon::setTestNow('2026-10-15 08:02:00');
    $this->service->ingest(odooLog());

    expect($this->repo->mapLoads)->toBe(3)
        ->and($this->repo->rows)->toHaveCount(1);
});

it('no recarga por los slugs conocidos', function () {
    Carbon::setTestNow('2026-10-15 09:00:00');

    $this->service->ingest(['app' => 'maya-logs', 'severity' => 'low', 'message' => 'x']);

    expect($this->repo->mapLoads)->toBe(1);
});

it('un slug vacío se descarta sin recargar', function () {
    Carbon::setTestNow('2026-10-15 09:00:00');

    expect(fn () => $this->service->ingest(['app' => '', 'severity' => 'low', 'message' => 'x']))
        ->toThrow(UnrecoverableIngestionException::class);
    expect($this->repo->mapLoads)->toBe(1);
});

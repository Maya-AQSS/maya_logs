<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Events\ArchivedLogFieldsWereUpdated;
use App\Events\ArchivedLogWasDeleted;
use App\Events\LogWasArchived;
use App\Models\ArchivedLog;
use App\Repositories\Contracts\ArchivedLogRepositoryInterface;
use App\Services\ArchivedLogService;
use Maya\Messaging\Publishers\ResilientLogPublisher;
use Illuminate\Support\Facades\Event;
use Maya\Messaging\Publishers\LogPublisher;
use Mockery;
use Tests\TestCase;

class ArchivedLogServiceArchiveEventTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_no_dispatch_cuando_el_repositorio_devuelve_un_archived_log_existente(): void
    {
        Event::fake([LogWasArchived::class]);

        $existing = new ArchivedLog();
        $existing->exists = true;
        $existing->id = 42;
        $existing->wasRecentlyCreated = false;

        $repo = Mockery::mock(ArchivedLogRepositoryInterface::class);
        $repo->shouldReceive('archiveFromLogId')
            ->once()
            ->with(7, 'user-subject')
            ->andReturn($existing);

        $publisher = new ResilientLogPublisher($this->createMock(LogPublisher::class));

        $sut = new ArchivedLogService($repo, $publisher);
        $out = $sut->archiveFromLogId(7, 'user-subject');

        $this->assertSame(42, $out->id);
        Event::assertNotDispatched(LogWasArchived::class);
    }

    public function test_dispatch_cuando_el_modelo_indica_creacion_reciente(): void
    {
        Event::fake([LogWasArchived::class]);

        $created = new ArchivedLog();
        $created->exists = true;
        $created->id = 99;
        $created->wasRecentlyCreated = true;

        $repo = Mockery::mock(ArchivedLogRepositoryInterface::class);
        $repo->shouldReceive('archiveFromLogId')
            ->once()
            ->andReturn($created);

        $publisher = new ResilientLogPublisher($this->createMock(LogPublisher::class));

        $sut = new ArchivedLogService($repo, $publisher);
        $sut->archiveFromLogId(1, 'actor-id');

        Event::assertDispatched(LogWasArchived::class, function (LogWasArchived $e) use ($created): bool {
            return $e->archivedLog->id === $created->id && $e->archivedByUserId === 'actor-id';
        });
    }

    public function test_update_no_emite_evento_si_los_valores_coinciden(): void
    {
        Event::fake([ArchivedLogFieldsWereUpdated::class]);

        $log = new ArchivedLog(['description' => 'igual', 'archived_by_id' => 'sub-1']);
        $log->id = 10;
        $log->exists = true;

        $repo = Mockery::mock(ArchivedLogRepositoryInterface::class);
        $repo->shouldReceive('updateArchivedFields')->never();

        $publisher = new ResilientLogPublisher($this->createMock(LogPublisher::class));
        $sut = new ArchivedLogService($repo, $publisher);

        $sut->updateArchivedFields($log, ['description' => 'igual']);

        Event::assertNotDispatched(ArchivedLogFieldsWereUpdated::class);
    }

    public function test_update_emite_evento_si_hay_cambio(): void
    {
        Event::fake([ArchivedLogFieldsWereUpdated::class]);

        $log = new ArchivedLog(['description' => 'viejo', 'archived_by_id' => 'sub-1']);
        $log->id = 11;
        $log->exists = true;

        $repo = Mockery::mock(ArchivedLogRepositoryInterface::class);
        $repo->shouldReceive('updateArchivedFields')
            ->once()
            ->with($log, ['description' => 'nuevo']);

        $publisher = new ResilientLogPublisher($this->createMock(LogPublisher::class));
        $sut = new ArchivedLogService($repo, $publisher);

        $sut->updateArchivedFields($log, ['description' => 'nuevo']);

        Event::assertDispatched(ArchivedLogFieldsWereUpdated::class, function (ArchivedLogFieldsWereUpdated $e): bool {
            return $e->archivedLogId === 11
                && $e->archivedByUserId === 'sub-1'
                && ($e->previousValue['description'] ?? null) === 'viejo'
                && ($e->newValue['description'] ?? null) === 'nuevo';
        });
    }

    public function test_delete_no_emite_evento_si_el_repositorio_devuelve_false(): void
    {
        Event::fake([ArchivedLogWasDeleted::class]);

        $log = new ArchivedLog();
        $log->id = 3;
        $log->archived_by_id = 'u';
        $log->exists = true;

        $repo = Mockery::mock(ArchivedLogRepositoryInterface::class);
        $repo->shouldReceive('delete')->once()->with($log)->andReturn(false);

        $publisher = new ResilientLogPublisher($this->createMock(LogPublisher::class));
        $sut = new ArchivedLogService($repo, $publisher);

        $sut->delete($log);

        Event::assertNotDispatched(ArchivedLogWasDeleted::class);
    }

    public function test_delete_emite_evento_si_el_repositorio_devuelve_true(): void
    {
        Event::fake([ArchivedLogWasDeleted::class]);

        $log = new ArchivedLog();
        $log->id = 4;
        $log->archived_by_id = 'u2';
        $log->exists = true;

        $repo = Mockery::mock(ArchivedLogRepositoryInterface::class);
        $repo->shouldReceive('delete')->once()->with($log)->andReturn(true);

        $publisher = new ResilientLogPublisher($this->createMock(LogPublisher::class));
        $sut = new ArchivedLogService($repo, $publisher);

        $sut->delete($log);

        Event::assertDispatched(ArchivedLogWasDeleted::class, function (ArchivedLogWasDeleted $e): bool {
            return $e->archivedLogId === 4 && $e->archivedByUserId === 'u2';
        });
    }
}

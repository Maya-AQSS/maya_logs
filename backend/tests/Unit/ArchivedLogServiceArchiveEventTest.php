<?php

declare(strict_types=1);

uses(\Tests\TestCase::class);

use App\Events\ArchivedLogFieldsWereUpdated;
use App\Events\ArchivedLogWasDeleted;
use App\Events\LogWasArchived;
use App\Models\ArchivedLog;
use App\Repositories\Contracts\ArchivedLogRepositoryInterface;
use App\Services\ArchivedFieldsValidator;
use App\Services\ArchivedLogService;
use App\Services\SeverityRankingService;
use Illuminate\Support\Facades\Event;
use Maya\Messaging\Publishers\LogPublisher;
use Maya\Messaging\Publishers\ResilientLogPublisher;

afterEach(function () {
    Mockery::close();
});

it('does not dispatch LogWasArchived when repository returns an existing archived log', function () {
    Event::fake([LogWasArchived::class]);

    $existing = new ArchivedLog();
    $existing->exists             = true;
    $existing->id                 = 42;
    $existing->wasRecentlyCreated = false;

    $repo = Mockery::mock(ArchivedLogRepositoryInterface::class);
    $repo->shouldReceive('archiveFromLogId')
        ->once()
        ->with(7, 'user-subject')
        ->andReturn($existing);

    $publisher = new ResilientLogPublisher($this->createMock(LogPublisher::class));

    $sut = new ArchivedLogService($repo, $publisher, new SeverityRankingService(), new ArchivedFieldsValidator());
    $out = $sut->archiveFromLogId(7, 'user-subject');

    expect($out->id)->toBe(42);
    Event::assertNotDispatched(LogWasArchived::class);
});

it('dispatches LogWasArchived when model indicates recent creation', function () {
    Event::fake([LogWasArchived::class]);

    $created = new ArchivedLog();
    $created->exists             = true;
    $created->id                 = 99;
    $created->wasRecentlyCreated = true;

    $repo = Mockery::mock(ArchivedLogRepositoryInterface::class);
    $repo->shouldReceive('archiveFromLogId')
        ->once()
        ->andReturn($created);

    $publisher = new ResilientLogPublisher($this->createMock(LogPublisher::class));

    $sut = new ArchivedLogService($repo, $publisher, new SeverityRankingService(), new ArchivedFieldsValidator());
    $sut->archiveFromLogId(1, 'actor-id');

    Event::assertDispatched(LogWasArchived::class, function (LogWasArchived $e) use ($created): bool {
        return $e->archivedLog->id === $created->id && $e->archivedByUserId === 'actor-id';
    });
});

it('does not emit event when update values match current values', function () {
    Event::fake([ArchivedLogFieldsWereUpdated::class]);

    $log = new ArchivedLog(['description' => 'igual', 'archived_by_id' => 'sub-1']);
    $log->id     = 10;
    $log->exists = true;

    $repo = Mockery::mock(ArchivedLogRepositoryInterface::class);
    $repo->shouldReceive('updateArchivedFields')->never();

    $publisher = new ResilientLogPublisher($this->createMock(LogPublisher::class));
    $sut = new ArchivedLogService($repo, $publisher, new SeverityRankingService(), new ArchivedFieldsValidator());

    $sut->updateArchivedFields($log, ['description' => 'igual']);

    Event::assertNotDispatched(ArchivedLogFieldsWereUpdated::class);
});

it('emits ArchivedLogFieldsWereUpdated when there is a change', function () {
    Event::fake([ArchivedLogFieldsWereUpdated::class]);

    $log = new ArchivedLog(['description' => 'viejo', 'archived_by_id' => 'sub-1']);
    $log->id     = 11;
    $log->exists = true;

    $repo = Mockery::mock(ArchivedLogRepositoryInterface::class);
    $repo->shouldReceive('updateArchivedFields')
        ->once()
        ->with($log, ['description' => 'nuevo']);

    $publisher = new ResilientLogPublisher($this->createMock(LogPublisher::class));
    $sut = new ArchivedLogService($repo, $publisher, new SeverityRankingService(), new ArchivedFieldsValidator());

    $sut->updateArchivedFields($log, ['description' => 'nuevo']);

    Event::assertDispatched(ArchivedLogFieldsWereUpdated::class, function (ArchivedLogFieldsWereUpdated $e): bool {
        return $e->archivedLogId === 11
            && $e->archivedByUserId === 'sub-1'
            && ($e->previousValue['description'] ?? null) === 'viejo'
            && ($e->newValue['description'] ?? null) === 'nuevo';
    });
});

it('does not emit ArchivedLogWasDeleted when repository returns false', function () {
    Event::fake([ArchivedLogWasDeleted::class]);

    $log = new ArchivedLog();
    $log->id             = 3;
    $log->archived_by_id = 'u';
    $log->exists         = true;

    $repo = Mockery::mock(ArchivedLogRepositoryInterface::class);
    $repo->shouldReceive('delete')->once()->with($log)->andReturn(false);

    $publisher = new ResilientLogPublisher($this->createMock(LogPublisher::class));
    $sut = new ArchivedLogService($repo, $publisher, new SeverityRankingService(), new ArchivedFieldsValidator());

    $sut->delete($log);

    Event::assertNotDispatched(ArchivedLogWasDeleted::class);
});

it('emits ArchivedLogWasDeleted when repository returns true', function () {
    Event::fake([ArchivedLogWasDeleted::class]);

    $log = new ArchivedLog();
    $log->id             = 4;
    $log->archived_by_id = 'u2';
    $log->exists         = true;

    $repo = Mockery::mock(ArchivedLogRepositoryInterface::class);
    $repo->shouldReceive('delete')->once()->with($log)->andReturn(true);

    $publisher = new ResilientLogPublisher($this->createMock(LogPublisher::class));
    $sut = new ArchivedLogService($repo, $publisher, new SeverityRankingService(), new ArchivedFieldsValidator());

    $sut->delete($log);

    Event::assertDispatched(ArchivedLogWasDeleted::class, function (ArchivedLogWasDeleted $e): bool {
        return $e->archivedLogId === 4 && $e->archivedByUserId === 'u2';
    });
});

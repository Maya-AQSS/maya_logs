<?php

declare(strict_types=1);

use App\Enums\ApplicationPluckScope;
use App\Repositories\Contracts\ApplicationRepositoryInterface;
use App\Services\ApplicationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

uses(\Tests\TestCase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    $this->repo = Mockery::mock(ApplicationRepositoryInterface::class);
    $this->service = new ApplicationService($this->repo);
});

afterEach(function () {
    Mockery::close();
    Cache::flush();
});

it('returns applications from repository via cache', function () {
    $expected = collect([1 => 'App One', 2 => 'App Two']);
    $this->repo->shouldReceive('pluckForFilter')
        ->with(ApplicationPluckScope::All)
        ->once()
        ->andReturn($expected);

    $result = $this->service->pluckForFilter(ApplicationPluckScope::All);

    expect($result)->toBe($expected);
});

it('caches the result on subsequent calls', function () {
    $expected = collect([1 => 'App One']);
    $this->repo->shouldReceive('pluckForFilter')
        ->once()
        ->andReturn($expected);

    $first = $this->service->pluckForFilter(ApplicationPluckScope::All);
    $second = $this->service->pluckForFilter(ApplicationPluckScope::All);

    expect($first)->toBe($second);
});

it('uses separate cache keys for different scopes', function () {
    $withLogs = collect([1 => 'App Logs']);
    $withArchived = collect([2 => 'App Archived']);

    $this->repo->shouldReceive('pluckForFilter')
        ->with(ApplicationPluckScope::WithLogs)
        ->once()
        ->andReturn($withLogs);

    $this->repo->shouldReceive('pluckForFilter')
        ->with(ApplicationPluckScope::WithArchivedLogs)
        ->once()
        ->andReturn($withArchived);

    $result1 = $this->service->pluckForFilter(ApplicationPluckScope::WithLogs);
    $result2 = $this->service->pluckForFilter(ApplicationPluckScope::WithArchivedLogs);

    expect($result1)->toBe($withLogs);
    expect($result2)->toBe($withArchived);
});

it('returns empty collection when repository returns empty', function () {
    $this->repo->shouldReceive('pluckForFilter')
        ->once()
        ->andReturn(collect([]));

    $result = $this->service->pluckForFilter(ApplicationPluckScope::All);

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toBeEmpty();
});

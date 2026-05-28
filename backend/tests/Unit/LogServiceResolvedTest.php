<?php

declare(strict_types=1);

uses(\Tests\TestCase::class);

use App\Models\Log;
use App\Repositories\Contracts\LogRepositoryInterface;
use App\Services\LogService;
use Maya\Messaging\Publishers\AuditPublisher;
use Maya\Messaging\Publishers\LogPublisher;
use Maya\Messaging\Publishers\ResilientLogPublisher;

it('calls findOrFail then repository resolved', function () {
    $log = $this->createMock(Log::class);

    $repository = $this->createMock(LogRepositoryInterface::class);
    $repository->expects($this->once())
        ->method('findOrFail')
        ->with(42)
        ->willReturn($log);
    $repository->expects($this->once())
        ->method('resolved')
        ->with(42);

    $auditPublisher = $this->createMock(AuditPublisher::class);
    // El audit se publica con el actor JWT; en este unit test no estamos
    // dentro de una DB::transaction, así que afterCommit ejecuta de inmediato.
    $auditPublisher->expects($this->once())
        ->method('publish');

    $resilientLogPublisher = new ResilientLogPublisher($this->createMock(LogPublisher::class));

    $service = new LogService($repository, $auditPublisher, $resilientLogPublisher);
    $service->resolved(42, 'jwt-subject-id');
});

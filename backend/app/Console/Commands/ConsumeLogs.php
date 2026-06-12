<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LogIngestionService;
use Maya\Messaging\Console\ConsumeQueueCommand;
use Maya\Messaging\Support\AmqpConsumer;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Consumidor de logs.ingest sobre la base compartida ConsumeQueueCommand
 * (shared-messaging-laravel), que encapsula la política de clasificación de
 * errores derivada de este mismo comando:
 *   - UnrecoverableIngestionException → ACK/drop (payload inválido)
 *   - QueryException → NACK/retry (fallo de infraestructura)
 *   - resto de Throwable → log + report + ACK/drop
 */
class ConsumeLogs extends ConsumeQueueCommand
{
    protected $signature = 'logs:consume {--queue= : Queue name (defaults to messaging.queues.logs_ingest config)}';

    protected $description = 'Consume logs.ingest from RabbitMQ and persist each log in the logs table';

    public function __construct(private readonly LogIngestionService $service)
    {
        parent::__construct();
    }

    public function queueName(): string
    {
        return (string) ($this->option('queue') ?: config('messaging.queues.logs_ingest', 'logs.ingest'));
    }

    public function ingest(array $payload, AMQPMessage $message): void
    {
        $this->service->ingest($payload);
    }

    /** Drena el buffer de inserciones batch al salir del loop de consumo. */
    public function flush(): void
    {
        $this->service->flush();
    }

    public function handle(AmqpConsumer $consumer): int
    {
        // Precarga el mapa slug→application_id antes de consumir (igual que antes).
        $this->service->loadApplicationMap();

        return parent::handle($consumer);
    }
}

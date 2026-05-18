<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LogIngestionService;
use Illuminate\Console\Command;
use Maya\Messaging\Support\AmqpConsumer;

class ConsumeLogs extends Command
{
    protected $signature = 'logs:consume {--queue=logs.ingest}';

    protected $description = 'Consume logs.ingest from RabbitMQ and persist each log in the logs table';

    public function handle(AmqpConsumer $consumer, LogIngestionService $service): int
    {
        $queue = (string) $this->option('queue');
        $this->info("Consuming from queue: {$queue}");

        $service->loadApplicationMap();
        $consumer->consume($queue, fn (array $payload) => $service->ingest($payload));
        $service->flush(); // Drain any logs remaining in the buffer after the consume loop exits.

        return self::SUCCESS;
    }
}

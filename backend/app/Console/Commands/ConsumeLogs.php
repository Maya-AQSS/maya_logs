<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\UnrecoverableIngestionException;
use App\Services\LogIngestionService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Maya\Messaging\Support\AmqpConsumer;

class ConsumeLogs extends Command
{
    protected $signature = 'logs:consume {--queue= : Queue name (defaults to messaging.queues.logs_ingest config)}';

    protected $description = 'Consume logs.ingest from RabbitMQ and persist each log in the logs table';

    public function handle(AmqpConsumer $consumer, LogIngestionService $service): int
    {
        $queue = (string) ($this->option('queue') ?: config('messaging.queues.logs_ingest', 'logs.ingest'));
        $this->info("Consuming from queue: {$queue}");

        $service->loadApplicationMap();

        $consumer->consume($queue, function (array $payload) use ($service): void {
            try {
                $service->ingest($payload);
            } catch (UnrecoverableIngestionException $e) {
                // Validation / business rule failure — drop the message so it does not block the queue.
                // AmqpConsumer will ACK after the handler returns normally.
                Log::warning('ConsumeLogs: dropping unrecoverable message', [
                    'error' => $e->getMessage(),
                    'payload_keys' => array_keys($payload),
                ]);
            } catch (QueryException $e) {
                // Infrastructure failure — rethrow so AmqpConsumer NACKs and retries.
                Log::error('ConsumeLogs: infrastructure error, will nack for retry', [
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            } catch (\Throwable $e) {
                // Unexpected error — log, report and drop to avoid queue blockage.
                Log::error('ConsumeLogs: unexpected error, dropping message', [
                    'error' => $e->getMessage(),
                    'payload_keys' => array_keys($payload),
                ]);
                report($e);
            }
        });

        $service->flush(); // Drain any logs remaining in the buffer after the consume loop exits.

        return self::SUCCESS;
    }
}

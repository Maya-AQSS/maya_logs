<?php

declare(strict_types=1);

namespace App\Notifications\Rules;

use App\Repositories\Contracts\LogRepositoryInterface;
use Illuminate\Support\Facades\Log as LogFacade;
use Maya\Messaging\Publishers\NotificationPublisher;
use Throwable;

class ErrorSpikeRule implements ScheduledNotificationRule
{
    public function __construct(
        private readonly LogRepositoryInterface $logRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function evaluate(NotificationPublisher $publisher, array $params, string $severity): int
    {
        try {
            $windowSeconds = (int) ($params['window_seconds'] ?? config('logs.error_spike_window_seconds', 60));
            $threshold = (int) ($params['threshold'] ?? config('logs.error_spike_threshold', 10));
            $windowStart = now()->subSeconds($windowSeconds);

            // Count critical and high severity logs in the window (data access via repo).
            $errorCount = $this->logRepository->countBySeveritiesSince(
                ['critical', 'high'],
                $windowStart,
            );

            if ($errorCount >= $threshold) {
                $this->publishAlert($publisher, $errorCount, $severity);

                return 1;
            }

            return 0;
        } catch (Throwable $e) {
            LogFacade::error('ErrorSpikeRule evaluation failed', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    private function publishAlert(NotificationPublisher $publisher, int $count, string $severity): void
    {
        try {
            // i18n resuelto en el dashboard por locale del usuario (claves +
            // params); severidad/url se rellenan desde la definición si se omiten.
            $publisher->send(
                type: 'logs.error_spike',
                recipientId: null,
                titleKey: 'notifications.logs.error_spike.title',
                bodyKey: 'notifications.logs.error_spike.body',
                params: ['count' => $count],
                severity: $severity,
                channels: ['app'],
                scope: 'dashboard',
            );
        } catch (Throwable $e) {
            LogFacade::error('ErrorSpikeRule notification publish failed', [
                'error' => $e->getMessage(),
                'count' => $count,
            ]);
        }
    }
}

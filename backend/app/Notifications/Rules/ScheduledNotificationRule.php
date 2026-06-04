<?php

declare(strict_types=1);

namespace App\Notifications\Rules;

use Maya\Messaging\Publishers\NotificationPublisher;

interface ScheduledNotificationRule
{
    /**
     * Evaluate the rule with the dashboard-configured params and publish.
     *
     * @param  array<string, mixed>  $params
     * @return int Number of notifications published
     */
    public function evaluate(NotificationPublisher $publisher, array $params, string $severity): int;
}

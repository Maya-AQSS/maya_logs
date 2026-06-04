<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NotificationRule;
use App\Models\NotificationRuleRun;
use App\Notifications\Rules\ErrorSpikeRule;
use App\Notifications\Rules\ScheduledNotificationRule;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Maya\Messaging\Publishers\NotificationPublisher;
use Throwable;

/**
 * Level B: reads this service's active scheduled rules from the dashboard
 * (notification_rules FDW view), computes cron due-ness against the local
 * run-state, and dispatches each due rule to its registered evaluator with
 * the admin-configured params/severity.
 */
class EvaluateNotificationRules extends Command
{
    protected $signature = 'notifications:evaluate-rules';

    protected $description = 'Evaluate dashboard-configured scheduled rules (FDW) and publish alerts';

    /**
     * @var array<string, class-string<ScheduledNotificationRule>>
     */
    private const EVALUATORS = [
        'logs.error_spike' => ErrorSpikeRule::class,
    ];

    public function handle(NotificationPublisher $publisher): int
    {
        $app = (string) config('messaging.app');
        $now = now();
        $total = 0;

        foreach (NotificationRule::query()->forApp($app)->get() as $rule) {
            try {
                if (! isset(self::EVALUATORS[$rule->evaluator_key]) || ! $this->isDue($rule, $now)) {
                    continue;
                }

                /** @var ScheduledNotificationRule $evaluator */
                $evaluator = app(self::EVALUATORS[$rule->evaluator_key]);
                $total += $evaluator->evaluate(
                    $publisher,
                    is_array($rule->params) ? $rule->params : [],
                    (string) ($rule->severity ?? 'info'),
                );

                $this->stampRun((int) $rule->id, $now);
            } catch (Throwable $e) {
                Log::error('notifications.rule_execution_failed', [
                    'rule_id' => $rule->id ?? null,
                    'evaluator_key' => $rule->evaluator_key ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Published {$total} notification(s)");

        return self::SUCCESS;
    }

    private function isDue(NotificationRule $rule, Carbon $now): bool
    {
        $cron = (string) $rule->schedule_cron;
        if (! CronExpression::isValidExpression($cron)) {
            return false;
        }

        $lastRun = NotificationRuleRun::query()->whereKey($rule->id)->value('last_run_at');
        $since = $lastRun !== null ? Carbon::parse($lastRun) : $now->copy()->subYear();

        return (new CronExpression($cron))->getNextRunDate($since->toDateTimeString()) <= $now;
    }

    private function stampRun(int $ruleId, Carbon $now): void
    {
        NotificationRuleRun::query()->updateOrCreate(['rule_id' => $ruleId], ['last_run_at' => $now]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\LogDto;
use App\Enums\Severity;
use App\Models\Log;
use App\Repositories\Contracts\LogRepositoryInterface;
use App\Services\Contracts\LogServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Maya\Http\Pagination\PaginatedDto;
use Maya\Messaging\Publishers\AuditPublisher;
use Maya\Messaging\Publishers\ResilientLogPublisher;
use Throwable;

class LogService implements LogServiceInterface
{
    private const AUDIT_ENTITY_TYPE = 'log';

    private const CODE_MARK_RESOLVED_FAILED = 'LAR-LOG-018';

    private const CODE_NOT_FOUND = 'LAR-LOG-019';

    public function __construct(
        private LogRepositoryInterface $logRepository,
        private AuditPublisher $auditPublisher,
        private ResilientLogPublisher $resilientLogPublisher,
    ) {}

    private function messagingAppSlug(): string
    {
        return (string) config('messaging.app');
    }

    public function paginate(int $perPage = 25): PaginatedDto
    {
        return PaginatedDto::fromPaginator(
            $this->logRepository->paginate($perPage),
            static fn (Log $m) => LogDto::fromModel($m),
        );
    }

    public function findOrFail(int $id): LogDto
    {
        try {
            return LogDto::fromModel($this->logRepository->findOrFail($id));
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                self::CODE_NOT_FOUND,
                ['log_id' => $id],
                $this->messagingAppSlug(),
            );
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findForShow(int $id): array
    {
        try {
            $result = $this->logRepository->findOrFailWithArchivedLogId($id);

            return [
                'dto' => LogDto::fromModel($result['log']),
                'archived_log_id' => $result['archived_log_id'],
            ];
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                self::CODE_NOT_FOUND,
                ['log_id' => $id],
                $this->messagingAppSlug(),
            );
            throw $e;
        }
    }

    public function streamPayload(int $limit = 10): array
    {
        $logs = $this->logRepository->latestForStream($limit);

        return $logs->map(function (Log $log): array {
            return [
                'id' => $log->id,
                'severity' => $log->severity,
                'message' => $log->message,
                'application' => $log->application?->name,
                'error_code' => $log->errorCode?->code,
                'created_at' => $log->created_at?->toIso8601String(),
            ];
        })->all();
    }

    public function searchAndFilter(
        ?string $search,
        ?array $severity,
        ?int $applicationId,
        ?string $archived,
        ?string $resolved,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $sortBy,
        ?string $sortDir,
        int $perPage = 25
    ): PaginatedDto {
        return PaginatedDto::fromPaginator(
            $this->logRepository->searchAndFilter(
                $search,
                $severity,
                $applicationId,
                $archived,
                $resolved,
                $dateFrom,
                $dateTo,
                $sortBy,
                $sortDir,
                $perPage
            ),
            static fn (Log $m) => LogDto::fromModel($m),
        );
    }

    /**
     * @return array{severity_cards: array<int, array{key:string,totalCount:int,resolvedCount:int,unresolvedCount:int}>, application_totals: array<int, array{application_id: int, name: string, total: int}>}
     */
    private function getDashboardAggregates(): array
    {
        return Cache::remember('dashboard:aggregates', now()->addSeconds(10), function (): array {
            $severityKeys = Severity::values();
            $bySeverity = $this->logRepository->severityResolvedCounts(true);
            $totalLogsCount = $this->logRepository->logsCount(true);

            $cards = collect($severityKeys)
                ->map(function (string $key) use ($bySeverity): array {
                    $resolvedCount = (int) ($bySeverity[$key]['resolved'] ?? 0);
                    $unresolvedCount = (int) ($bySeverity[$key]['unresolved'] ?? 0);

                    return $this->buildDashboardCard(
                        key: $key,
                        resolvedCount: $resolvedCount,
                        unresolvedCount: $unresolvedCount,
                    );
                })
                ->values();

            $allResolved = (int) $cards->sum('resolvedCount');
            $allUnresolved = (int) $cards->sum('unresolvedCount');

            $cards->prepend($this->buildDashboardCard(
                key: 'all',
                resolvedCount: $allResolved,
                unresolvedCount: $allUnresolved,
                totalCount: $totalLogsCount,
            ));

            return [
                'severity_cards' => $cards->all(),
                'application_totals' => $this->logRepository->applicationTotals(true),
            ];
        });
    }

    public function dashboardSeverityCards(): array
    {
        return $this->getDashboardAggregates()['severity_cards'];
    }

    public function dashboardApplicationTotals(): array
    {
        return $this->getDashboardAggregates()['application_totals'];
    }

    public function archivedLogIdFor(int $logId): ?int
    {
        return $this->logRepository->archivedLogIdFor($logId);
    }

    /**
     * Marca el log como resuelto.
     *
     * Si el log no existe o falla la persistencia se publica incidencia a maya.logs
     * (LAR-LOG-018) y se relanza. Si la operación tiene efecto, publica a maya.audit
     * vía AuditPublisher con el actor JWT — RetryAmqpPublishJob recupera fallos
     * (LAR-LOG-020 documenta la incidencia AMQP).
     */
    public function resolved(int $logId, string $actorUserId): void
    {
        try {
            $this->logRepository->findOrFail($logId);
            $this->logRepository->resolved($logId);

            $this->afterCommit(function () use ($logId, $actorUserId): void {
                $this->auditPublisher->publish(
                    applicationSlug: $this->messagingAppSlug(),
                    entityType: self::AUDIT_ENTITY_TYPE,
                    entityId: (string) $logId,
                    action: 'Marcar un log como resuelto',
                    userId: $actorUserId,
                    previousValue: ['resolved' => false],
                    newValue: ['resolved' => true],
                );
            });
        } catch (Throwable $e) {
            $this->resilientLogPublisher->publishFromThrowable(
                $e,
                'medium',
                self::CODE_MARK_RESOLVED_FAILED,
                ['log_id' => $logId, 'actor_user_id' => $actorUserId],
                $this->messagingAppSlug(),
            );
            throw $e;
        }
    }

    /**
     * Sin transacción activa, publica de inmediato; dentro de DB::transaction difiere al commit.
     */
    private function afterCommit(callable $callback): void
    {
        if (DB::transactionLevel() === 0) {
            $callback();

            return;
        }

        DB::afterCommit($callback);
    }

    /**
     * @return array{key:string,totalCount:int,resolvedCount:int,unresolvedCount:int}
     */
    private function buildDashboardCard(
        string $key,
        int $resolvedCount,
        int $unresolvedCount,
        ?int $totalCount = null,
    ): array {
        return [
            'key' => $key,
            'totalCount' => $totalCount ?? ($resolvedCount + $unresolvedCount),
            'resolvedCount' => $resolvedCount,
            'unresolvedCount' => $unresolvedCount,
        ];
    }
}

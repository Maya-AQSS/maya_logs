<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\LogDto;
use App\Enums\Severity;
use App\Models\Log;
use App\Repositories\Contracts\LogRepositoryInterface;
use App\Services\Contracts\LogServiceInterface;
use Illuminate\Support\Facades\Cache;
use Maya\Http\Pagination\PaginatedDto;

class LogService implements LogServiceInterface
{
    public function __construct(
        private LogRepositoryInterface $logRepository
    ) {}

    public function paginate(int $perPage = 25): PaginatedDto
    {
        return PaginatedDto::fromPaginator(
            $this->logRepository->paginate($perPage),
            static fn (Log $m) => LogDto::fromModel($m),
        );
    }

    public function findOrFail(int $id): LogDto
    {
        return LogDto::fromModel($this->logRepository->findOrFail($id));
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

    public function resolved(int $logId): void
    {
        $this->logRepository->findOrFail($logId);
        $this->logRepository->resolved($logId);
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

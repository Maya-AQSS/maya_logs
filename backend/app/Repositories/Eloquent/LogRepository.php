<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\Severity;
use App\Models\ArchivedLog;
use App\Models\Log;
use App\Repositories\Contracts\LogRepositoryInterface;
use App\Support\LikeEscaper;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LogRepository implements LogRepositoryInterface
{
    // Mantener la constante para compatibilidad con queries existentes
    private const LIKE_ESCAPE_CHARACTER = LikeEscaper::LIKE_ESCAPE_CHARACTER;

    private const SORT_COLUMN_MAP = [
        'created_at' => 'logs.created_at',
        'severity' => 'logs.severity',
        'application' => 'applications.name',
    ];

    private const SORT_DIRECTIONS = ['asc', 'desc'];

    /**
     * Devuelve una página de logs.
     */
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return Log::query()
            ->with(['application', 'errorCode'])
            ->latest('created_at')
            ->paginate($perPage);
    }

    /**
     * Busca un log por su id.
     */
    public function findOrFail(int $id): Log
    {
        return Log::query()
            ->with(['application', 'errorCode'])
            ->findOrFail($id);
    }

    /**
     * Devuelve los últimos logs para streaming en tiempo real.
     */
    public function latestForStream(int $limit = 10): Collection
    {
        return Log::query()
            ->with(['application', 'errorCode'])
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Busca y filtra logs por diferentes criterios:
     * - texto libre en el mensaje
     * - tipo de severidad de error
     * - aplicación (application_id)
     * - si está archivado o no
     * - si está resuelto o no
     */
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
    ): LengthAwarePaginator {
        $archivedFlagSubquery = ArchivedLog::query()->selectRaw('1');
        $this->applyArchivedMatchForLogsQuery($archivedFlagSubquery);

        $normalizedSearch = $search !== null && trim($search) !== ''
            ? trim($search)
            : null;

        $escapedSearchPattern = $normalizedSearch !== null
            ? '%'.LikeEscaper::escapeLikePattern($normalizedSearch).'%'
            : null;

        $driver = DB::connection()->getDriverName();

        $validatedSortDirection = in_array($sortDir, self::SORT_DIRECTIONS, true) ? $sortDir : 'asc';
        $sortColumn = $sortBy !== null ? (self::SORT_COLUMN_MAP[$sortBy] ?? null) : null;

        $query = Log::query()
            ->select('logs.*')
            ->addSelect([
                'is_archived' => $archivedFlagSubquery->limit(1),
            ])
            ->with(['application', 'errorCode']);

        if ($sortBy === 'application') {
            $query->leftJoin('applications', 'applications.id', '=', 'logs.application_id');
        }

        return $query
            ->when($normalizedSearch !== null, function ($q) use ($driver, $normalizedSearch, $escapedSearchPattern): void {
                if ($driver === 'pgsql') {
                    $q->whereRaw("message ILIKE ? ESCAPE '".self::LIKE_ESCAPE_CHARACTER."'", [$escapedSearchPattern]);

                    return;
                }

                // Fallback for non-PostgreSQL test environments without wildcard semantics.
                $q->whereRaw('INSTR(LOWER(message), ?) > 0', [mb_strtolower($normalizedSearch)]);
            })
            ->when($severity, fn ($q) => $q->whereIn('severity', $severity))
            ->when($applicationId !== null, fn ($q) => $q->where('application_id', $applicationId))
            ->when($archived, function ($q) use ($archived): void {
                if ($archived === 'only') {
                    $q->whereExists(fn ($subQuery) => $this->applyArchivedMatchForLogsQuery($subQuery));
                } elseif ($archived === 'without') {
                    $q->whereNotExists(fn ($subQuery) => $this->applyArchivedMatchForLogsQuery($subQuery));
                }
            })
            ->when($resolved, function ($q) use ($resolved): void {
                if ($resolved === 'only') {
                    $q->where('resolved', true);
                } elseif ($resolved === 'unresolved') {
                    $q->where('resolved', false);
                }
            })
            ->when($dateFrom, fn ($q) => $q->where('logs.created_at', '>=', CarbonImmutable::parse($dateFrom)->utc()->toDateTimeString()))
            ->when($dateTo, fn ($q) => $q->where('logs.created_at', '<=', CarbonImmutable::parse($dateTo)->utc()->toDateTimeString()))
            ->when($dateFrom && ! $dateTo, fn ($q) => $q->where('logs.created_at', '<=', now()->utc()->toDateTimeString()))
            ->when(
                $sortColumn !== null,
                fn ($q) => $q->orderBy($sortColumn, $validatedSortDirection),
                fn ($q) => $q->orderBy('logs.created_at', 'desc')
            )
            ->paginate($perPage);
    }

    /**
     * Devuelve el número de logs por severidad y estado resolved/unresolved.
     * No construye la card "Todos": solo devuelve buckets por severidad.
     * Si $includeArchived es false, excluye logs con equivalente en archived_logs.
     *
     * @return array<string,array{resolved:int,unresolved:int,total:int}>
     */
    public function severityResolvedCounts(bool $includeArchived = false): array
    {
        $severities = Severity::values();

        $rows = Log::query()
            ->when(! $includeArchived, fn ($q) => $q->whereNotExists(fn ($subQuery) => $this->applyArchivedMatchForLogsQuery($subQuery)))
            ->selectRaw('severity, resolved, count(*) as count')
            ->whereIn('severity', $severities)
            ->groupBy('severity', 'resolved')
            ->get();

        $result = [];
        foreach ($severities as $severity) {
            $result[$severity] = [
                'resolved' => 0,
                'unresolved' => 0,
                'total' => 0,
            ];
        }

        foreach ($rows as $row) {
            $severity = (string) $row->severity;
            $count = (int) $row->count;
            $bucket = (bool) $row->resolved ? 'resolved' : 'unresolved';

            if (! isset($result[$severity])) {
                continue;
            }

            $result[$severity][$bucket] += $count;
            $result[$severity]['total'] += $count;
        }

        return $result;
    }

    /**
     * Devuelve el número total de logs para el scope solicitado.
     * Si $includeArchived es false, excluye logs con equivalente en archived_logs.
     */
    public function logsCount(bool $includeArchived = false): int
    {
        return Log::query()
            ->when(! $includeArchived, fn ($q) => $q->whereNotExists(fn ($subQuery) => $this->applyArchivedMatchForLogsQuery($subQuery)))
            ->count();
    }

    /**
     * {@inheritdoc}
     */
    public function applicationTotals(bool $includeArchived = true): array
    {
        $rows = Log::query()
            ->when(! $includeArchived, fn ($q) => $q->whereNotExists(fn ($subQuery) => $this->applyArchivedMatchForLogsQuery($subQuery)))
            ->join('applications', 'applications.id', '=', 'logs.application_id')
            ->select('logs.application_id', 'applications.name as application_name', DB::raw('COUNT(*) as total'))
            ->groupBy('logs.application_id', 'applications.name')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($row): array => [
            'application_id' => (int) $row->application_id,
            'name' => (string) $row->application_name,
            'total' => (int) $row->total,
        ])->all();
    }

    /**
     * Devuelve el id de ArchivedLog equivalente al log o null si no está archivado.
     */
    public function archivedLogIdFor(int $logId): ?int
    {
        $log = Log::query()
            ->whereKey($logId)
            ->first();

        if ($log === null) {
            return null;
        }

        $archivedQuery = ArchivedLog::query();
        $this->applyArchivedMatchForConcreteLog($archivedQuery, $log);
        $archivedId = $archivedQuery
            ->orderByDesc('archived_at')
            ->orderByDesc('id')
            ->value('id');

        return $archivedId !== null ? (int) $archivedId : null;
    }

    /**
     * Marca el log como resuelto.
     *
     * Se usa el query builder: el modelo {@see Log} cancela actualizaciones vía Eloquent en {@see Log::booted()}.
     */
    public function resolved(int $logId): void
    {
        DB::table('logs')->where('id', $logId)->update(['resolved' => true]);
    }

    /**
     * Aplica a una subquery la condición de equivalencia lógica
     * archived_logs <-> logs (sin FK; misma aplicación, código de error, severidad y mensaje).
     *
     * Se usa para whereExists/whereNotExists y para calcular is_archived.
     */
    private function applyArchivedMatchForLogsQuery(Builder|QueryBuilder $query): Builder|QueryBuilder
    {
        return $query
            ->from('archived_logs')
            ->whereColumn('archived_logs.application_id', 'logs.application_id')
            ->whereRaw('archived_logs.error_code_id IS NOT DISTINCT FROM logs.error_code_id')
            ->whereColumn('archived_logs.severity', 'logs.severity')
            ->whereColumn('archived_logs.message', 'logs.message');
    }

    /**
     * Misma equivalencia lógica para un Log concreto.
     * Se usa para obtener el id del ArchivedLog equivalente (si hay varios, el más reciente por archivado).
     */
    private function applyArchivedMatchForConcreteLog(Builder $query, Log $log): Builder
    {
        return $query
            ->where('application_id', $log->application_id)
            ->whereRaw('error_code_id IS NOT DISTINCT FROM ?', [$log->error_code_id])
            ->where('severity', $log->severity)
            ->where('message', $log->message);
    }
}

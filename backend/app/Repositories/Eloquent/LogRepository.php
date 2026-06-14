<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Dtos\LogDto;
use App\Dtos\LogFilterDto;
use App\Enums\Severity;
use App\Models\ArchivedLog;
use App\Models\Log;
use App\Repositories\Contracts\LogRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Maya\Search\AccentFold;

class LogRepository implements LogRepositoryInterface
{
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
     * Busca y filtra logs aplicando los criterios del LogFilterDto:
     * - texto libre en el mensaje
     * - severidad (uno o varios valores)
     * - aplicación por slug (join con applications)
     * - código de error por code string (join con error_codes)
     * - rango de fechas (created_at)
     * - estado archivado / resuelto
     */
    public function searchAndFilter(LogFilterDto $filter): LengthAwarePaginator
    {
        $archivedFlagSubquery = ArchivedLog::query()->selectRaw('1');
        $this->applyArchivedMatchForLogsQuery($archivedFlagSubquery);

        $normalizedSearch = $filter->search !== null && trim($filter->search) !== ''
            ? trim($filter->search)
            : null;

        // Accent-folding compartido (Maya\Search\AccentFold): el needle se pliega
        // en PHP (lowercase + sin acentos) y las columnas se pliegan en SQL de
        // forma driver-aware, de modo que "facturacion" encuentra "Facturación".
        // Escape de comodines LIKE con backslash (convención del paquete) — ya no
        // se necesita cláusula ESCAPE '!'.
        $escapedSearchPattern = $normalizedSearch !== null
            ? '%'.AccentFold::escapeLike(AccentFold::fold($normalizedSearch)).'%'
            : null;

        $driver = DB::connection()->getDriverName();

        $sortDir = in_array($filter->sortDir, self::SORT_DIRECTIONS, true) ? $filter->sortDir : 'desc';
        $sortColumn = $filter->sortBy !== null ? (self::SORT_COLUMN_MAP[$filter->sortBy] ?? null) : null;

        $needsApplicationJoin = $filter->sortBy === 'application' || $filter->appSlug !== null;

        $query = Log::query()
            ->select('logs.*')
            ->addSelect([
                'is_archived' => $archivedFlagSubquery->limit(1),
            ])
            ->with(['application', 'errorCode']);

        if ($needsApplicationJoin) {
            $query->leftJoin('applications', 'applications.id', '=', 'logs.application_id');
        }

        // El join con error_codes es necesario tanto para filtrar por código como
        // para que la búsqueda de texto pueda cubrir el código y nombre del error.
        if ($filter->errorCode !== null || $normalizedSearch !== null) {
            $query->leftJoin('error_codes', 'error_codes.id', '=', 'logs.error_code_id');
        }

        return $query
            ->when($normalizedSearch !== null, function ($q) use ($driver, $escapedSearchPattern): void {
                // La búsqueda cubre: mensaje, código y nombre del error asociado, y fichero.
                // sqlFoldedLowerColumn: pgsql pliega acentos en SQL; sqlite solo lower()
                // (el needle ya viene plegado en PHP — mismas garantías que antes).
                $q->where(function ($inner) use ($driver, $escapedSearchPattern): void {
                    $first = true;
                    foreach (['logs.message', 'error_codes.code', 'error_codes.name', 'logs.file'] as $column) {
                        [$expr, $bindings] = AccentFold::sqlFoldedLowerColumn($column, $driver);
                        $sql = "{$expr} LIKE ?";
                        $params = [...$bindings, $escapedSearchPattern];

                        $first ? $inner->whereRaw($sql, $params) : $inner->orWhereRaw($sql, $params);
                        $first = false;
                    }
                });
            })
            ->when($filter->severity, fn ($q) => $q->whereIn('logs.severity', $filter->severity))
            ->when($filter->applicationId !== null, fn ($q) => $q->where('logs.application_id', $filter->applicationId))
            ->when($filter->appSlug !== null, fn ($q) => $q->where('applications.slug', $filter->appSlug))
            ->when($filter->errorCode !== null, fn ($q) => $q->where('error_codes.code', $filter->errorCode))
            ->when($filter->archived, function ($q) use ($filter): void {
                if ($filter->archived === 'only') {
                    $q->whereExists(fn ($subQuery) => $this->applyArchivedMatchForLogsQuery($subQuery));
                } elseif ($filter->archived === 'without') {
                    $q->whereNotExists(fn ($subQuery) => $this->applyArchivedMatchForLogsQuery($subQuery));
                }
            })
            ->when($filter->resolved, function ($q) use ($filter): void {
                if ($filter->resolved === 'only') {
                    $q->where('resolved', true);
                } elseif ($filter->resolved === 'unresolved') {
                    $q->where('resolved', false);
                }
            })
            ->when($filter->from, fn ($q) => $q->where('logs.created_at', '>=', Date::parse($filter->from)->utc()->toDateTimeString()))
            ->when($filter->to, fn ($q) => $q->where('logs.created_at', '<=', Date::parse($filter->to)->utc()->toDateTimeString()))
            ->when($filter->from && ! $filter->to, fn ($q) => $q->where('logs.created_at', '<=', now()->utc()->toDateTimeString()))
            ->when(
                $sortColumn !== null,
                fn ($q) => $q->orderBy($sortColumn, $sortDir),
                fn ($q) => $q->orderBy('logs.created_at', 'desc')
            )
            ->paginate($filter->perPage, ['*'], 'page', $filter->page);
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
     * Carga el log (con relaciones) y resuelve el id del ArchivedLog equivalente
     * en una sola ronda de base de datos: un SELECT con subquery LEFT JOIN.
     *
     * @return array{log: Log, archived_log_id: int|null}
     *
     * @throws ModelNotFoundException
     */
    public function findOrFailWithArchivedLogId(int $id): array
    {
        $archivedIdSubquery = ArchivedLog::query()
            ->select('id')
            ->whereColumn('application_id', 'logs.application_id')
            ->whereRaw('error_code_id IS NOT DISTINCT FROM logs.error_code_id')
            ->whereColumn('severity', 'logs.severity')
            ->whereColumn('message', 'logs.message')
            ->orderByDesc('archived_at')
            ->orderByDesc('id')
            ->limit(1);

        /** @var Log $log */
        $log = Log::query()
            ->with(['application', 'errorCode'])
            ->selectRaw('logs.*')
            ->selectSub($archivedIdSubquery, 'archived_log_id')
            ->findOrFail($id);

        $rawArchivedId = $log->getAttribute('archived_log_id');

        return [
            'log' => $log,
            'archived_log_id' => $rawArchivedId !== null ? (int) $rawArchivedId : null,
        ];
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

    /**
     * Devuelve los DTOs más recientes para streaming en tiempo real.
     *
     * @return Collection<int, LogDto>
     */
    public function streamPayloadDtos(int $limit = 10): Collection
    {
        return $this->latestForStream($limit)->map(
            static fn (Log $m) => LogDto::fromModel($m)
        );
    }

    /**
     * Indica si hay una transacción activa en la BD.
     */
    public function isInTransaction(): bool
    {
        return DB::transactionLevel() > 0;
    }

    /**
     * Cuenta los logs con alguna de las severidades dadas creados desde $since (inclusive).
     *
     * @param  list<string>  $severities
     */
    public function countBySeveritiesSince(array $severities, CarbonInterface $since): int
    {
        if ($severities === []) {
            return 0;
        }

        return Log::query()
            ->whereIn('severity', $severities)
            ->where('created_at', '>=', $since)
            ->count();
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ErrorCode;
use App\Repositories\Contracts\ErrorCodeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Maya\Search\AccentFold;

class ErrorCodeRepository implements ErrorCodeRepositoryInterface
{
    private const SORT_COLUMN_MAP = [
        'code' => 'error_codes.code',
        'application' => 'applications.name',
        'name' => 'error_codes.name',
        'file' => 'error_codes.file',
        'line' => 'error_codes.line',
    ];

    private const SORT_DIRECTIONS = ['asc', 'desc'];

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return ErrorCode::query()
            ->with('application')
            ->withCount(['logs', 'archivedLogs', 'comments'])
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function searchAndFilter(
        ?string $search,
        ?int $filterApp,
        ?string $sortBy = null,
        ?string $sortDir = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $driver = DB::connection()->getDriverName();

        // Accent-folding compartido (Maya\Search\AccentFold): needle plegado en PHP,
        // columnas plegadas en SQL driver-aware ("facturacion" encuentra "Facturación").
        // Escape de comodines con backslash — sin cláusula ESCAPE '!'.
        $escapedSearch = $search !== null && trim($search) !== ''
            ? AccentFold::escapeLike(AccentFold::fold(trim($search)))
            : null;

        $sortDir = in_array($sortDir, self::SORT_DIRECTIONS, true) ? $sortDir : 'asc';
        $sortColumn = $sortBy !== null ? (self::SORT_COLUMN_MAP[$sortBy] ?? null) : null;

        $needsApplicationJoin = $sortBy === 'application' || $filterApp !== null;

        $query = ErrorCode::query()
            ->select('error_codes.*')
            ->with('application')
            ->withCount(['logs', 'archivedLogs', 'comments']);

        if ($needsApplicationJoin) {
            $query->leftJoin('applications', 'applications.id', '=', 'error_codes.application_id');
        }

        return $query
            ->when($escapedSearch !== null, function ($query) use ($driver, $escapedSearch) {
                $pattern = '%'.$escapedSearch.'%';

                [$codeExpr, $codeBindings] = AccentFold::sqlFoldedLowerColumn('code', $driver);
                [$nameExpr, $nameBindings] = AccentFold::sqlFoldedLowerColumn('name', $driver);

                $query->where(function ($query) use ($pattern, $codeExpr, $codeBindings, $nameExpr, $nameBindings) {
                    $query->whereRaw("{$codeExpr} LIKE ?", [...$codeBindings, $pattern])
                        ->orWhereRaw("{$nameExpr} LIKE ?", [...$nameBindings, $pattern]);
                });
            })
            ->when($filterApp, fn ($query, $filterApp) => $query->where('application_id', $filterApp))
            ->when(
                $sortColumn !== null,
                fn ($q) => $q->orderBy($sortColumn, $sortDir),
                fn ($q) => $q->orderBy('error_codes.code', 'asc')
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(int $id): ErrorCode
    {
        return ErrorCode::query()
            ->with('application')
            ->findOrFail($id);
    }

    public function create(array $data): ErrorCode
    {
        return ErrorCode::query()->create($data);
    }

    public function update(int $id, array $data): ErrorCode
    {
        $errorCode = ErrorCode::query()->findOrFail($id);
        $errorCode->fill($data);
        $errorCode->save();

        return $errorCode;
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $errorCode = ErrorCode::query()->findOrFail($id);
            $errorCode->delete();
        });
    }
}

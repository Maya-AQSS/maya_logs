<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ErrorCode;
use App\Repositories\Contracts\ErrorCodeRepositoryInterface;
use App\Support\LikeEscaper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ErrorCodeRepository implements ErrorCodeRepositoryInterface
{
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
        int $perPage = 15
    ): LengthAwarePaginator {
        $driver = DB::connection()->getDriverName();
        $escapedSearch = $search !== null && trim($search) !== ''
            ? LikeEscaper::escapeLikePattern(trim($search))
            : null;

        return ErrorCode::query()
            ->with('application')
            ->withCount(['logs', 'archivedLogs', 'comments'])
            ->when($escapedSearch !== null, function ($query) use ($driver, $escapedSearch) {
                $pattern = '%'.$escapedSearch.'%';
                $esc = LikeEscaper::LIKE_ESCAPE_CHARACTER;
                if ($driver === 'pgsql') {
                    $query->where(function ($query) use ($pattern, $esc) {
                        $query->whereRaw("code ILIKE ? ESCAPE '".$esc."'", [$pattern])
                            ->orWhereRaw("name ILIKE ? ESCAPE '".$esc."'", [$pattern]);
                    });
                } else {
                    /*
                    SQLite u otros: LIKE con ESCAPE (misma semántica de comodines) y LOWER para aproximar ILIKE.
                    */
                    $query->where(function ($query) use ($pattern, $esc) {
                        $query->whereRaw("LOWER(code) LIKE LOWER(?) ESCAPE '".$esc."'", [$pattern])
                            ->orWhereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '".$esc."'", [$pattern]);
                    });
                }
            })
            ->when($filterApp, fn ($query, $filterApp) => $query->where('application_id', $filterApp))
            ->orderBy('code')
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

    public function update(ErrorCode $errorCode, array $data): ErrorCode
    {
        $errorCode->fill($data);
        $errorCode->save();

        return $errorCode;
    }

    public function delete(ErrorCode $errorCode): void
    {
        $errorCode->delete();
    }
}

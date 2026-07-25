<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

// Shared query helpers for Api\*Controller@index methods, factored out of the
// pattern AssetController::filteredQuery() established first (search + exact
// filters + whitelisted sort + clamped-per_page pagination). Response shape is
// always Laravel's default paginate() envelope — same as AssetController.
trait PaginatesAndSorts
{
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * @param  array<int, string>  $columns  Plain columns on the model itself.
     * @param  array<string, array<int, string>>  $relations  relation => [columns] searched via orWhereHas.
     */
    protected function applySearch(Builder $query, Request $request, array $columns, array $relations = []): void
    {
        $search = trim((string) $request->query('search', ''));
        if ($search === '') {
            return;
        }

        $query->where(function ($q) use ($search, $columns, $relations) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', "%{$search}%");
            }
            foreach ($relations as $relation => $relationColumns) {
                $q->orWhereHas($relation, function ($relationQuery) use ($relationColumns, $search) {
                    $relationQuery->where(function ($rq) use ($relationColumns, $search) {
                        foreach ($relationColumns as $column) {
                            $rq->orWhere($column, 'like', "%{$search}%");
                        }
                    });
                });
            }
        });
    }

    /** @param  array<int, string>  $fields  Exact-match `where($field, $value)` for each present, non-empty query param. */
    protected function applyExactFilters(Builder $query, Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }
    }

    /** @param  array<int, string>  $sortable  Whitelist of columns allowed in `sort_by`. */
    protected function paginateSorted(
        Builder $query,
        Request $request,
        array $sortable,
        string $defaultSort = 'created_at',
        string $defaultDir = 'desc',
    ) {
        $sortBy = $request->query('sort_by', $defaultSort);
        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = $defaultSort;
        }
        $sortDir = $request->query('sort_dir', $defaultDir) === 'asc' ? 'asc' : 'desc';

        $perPage = (int) $request->query('per_page', 25);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 25;

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage)->withQueryString();
    }
}

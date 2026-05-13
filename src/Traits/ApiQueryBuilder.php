<?php

namespace PowerVending\LaravelApiQueryBuilder\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use PowerVending\LaravelApiQueryBuilder\ApiQuery;

trait ApiQueryBuilder
{
    /**
     * Scope a query from request parameters.
     *
     * @param Builder $query
     * @param array|null $input Query parameters. If null, uses request()->all()
     * @return Builder
     */
    public function scopeRequestQuery(Builder $query, ?array $input = null): Builder
    {
        (new ApiQuery($query, $input ?? request()->all()))->search();
        return $query;
    }

    /**
     * Scope a paginated query from request parameters.
     *
     * @param Builder $query
     * @param array|null $input Query parameters. If null, uses request()->all()
     * @return LengthAwarePaginator
     */
    public function scopeRequestPaginate(Builder $query, ?array $input = null, int $perPage = 25): LengthAwarePaginator
    {
        $input = $input ?? request()->all();

        if (request()->has('_per_page')) {
            \Illuminate\Support\Facades\Validator::make(
                ['_per_page' => request()->_per_page],
                ['_per_page' => 'nullable|integer|min:1']
            )->validate();
        }

        $perPage = request()->_per_page ?? $perPage;

        (new ApiQuery($query, $input))->search();

        return $query->paginate($perPage);
    }

    /**
     * Boot method for the ApiQueryBuilder trait.
     */
    public static function bootApiQueryBuilder()
    {
        // Boot method for future use if needed
    }
}

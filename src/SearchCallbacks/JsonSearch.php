<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\SearchCallbacks;

use Illuminate\Database\Eloquent\Builder;
use PowerVending\LaravelApiQueryBuilder\CategorizedValues;
use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;

class JsonSearch extends AbstractCallback
{
    public static function operator(): string
    {
        return 'JSON_SEARCH:';
    }

    public static function supportsTextTypes(): bool
    {
        return false;
    }

    public static function supportsComparableTypes(): bool
    {
        return false;
    }

    public static function supportsJsonTypes(): bool
    {
        return true;
    }

    /**
     * MySQL JSON_SEARCH: returns path or NULL; we filter by IS NOT NULL.
     *
     * Always uses the 'one' mode and allows SQL wildcards in the search string.
     *
     * @throws ApiQueryBuilderException
     */
    public function execute(Builder $builder, string $column, CategorizedValues $values): void
    {
        if ($values->not || $values->notLike || $values->null || $values->notNull) {
            throw new ApiQueryBuilderException("Wrong parameter type(s) for 'json_search' operator.");
        }

        $searchValues = array_merge($values->and, $values->andLike);

        if (count($searchValues) < 1) {
            throw new ApiQueryBuilderException("No valid arguments for 'json_search' operator.");
        }

        foreach (array_values($searchValues) as $index => $value) {
            $wrappedColumn = $builder->getQuery()->grammar->wrap($column);
            $sql = "JSON_SEARCH({$wrappedColumn}, 'one', ?) IS NOT NULL";

            if ($index === 0) {
                $builder->whereRaw($sql, [$value]);
                continue;
            }

            $builder->orWhereRaw($sql, [$value]);
        }
    }
}

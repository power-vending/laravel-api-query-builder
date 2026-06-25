<?php

declare(strict_types=1);

namespace PowerVending\LaravelApiQueryBuilder\SearchCallbacks;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PowerVending\LaravelApiQueryBuilder\{CategorizedValues, CustomFieldSearchParser, SearchParserInterface};
use PowerVending\LaravelApiQueryBuilder\Exceptions\{ApiQueryBuilderException, InvalidOperatorUsageException};

abstract class AbstractCallback
{
    protected const DATE_FIELDS = [
        'date',
    ];

    protected Builder $builder;

    protected SearchParserInterface $searchParser;

    protected CategorizedValues $categorizedValues;

    /**
     * AbstractCallback constructor.
     *
     * @param  Builder  $builder
     * @param  SearchParserInterface  $searchParser
     *
     * @throws ApiQueryBuilderException
     */
    public function __construct(Builder $builder, SearchParserInterface $searchParser)
    {
        $this->builder = $builder;
        $this->searchParser = $searchParser;
        $this->categorizedValues = new CategorizedValues($this->searchParser);

        $this->builder->when(
            $this->searchParser->isModelRelation(),
            function (Builder $builder) {
                // Hack for whereDoesntHave relation, doesn't work recursively.
                if (str_contains($this->searchParser->column, '!') !== false) {
                    $this->searchParser->column = str_replace('!', '', $this->searchParser->column);
                    $this->appendRelations($builder, $this->searchParser->column, $this->categorizedValues, 'orWhereDoesntHave');

                    return;
                }
                $this->appendRelations($builder, $this->searchParser->column, $this->categorizedValues);
            },
            function (Builder $builder) {
                $column = $this->qualifyColumnName($this->searchParser->column);
                $this->execute($builder, $column, $this->categorizedValues);
                $this->checkExecuteForCustomfieldsParameter($builder);
            }
        );
    }

    /**
     * Shorthand operator sign.
     *
     * I.e. '=', '<', '>'...
     *
     * @return string
     */
    abstract public static function operator(): string;

    /**
     * Whether this operator can be used on text-type columns (string, varchar, text, etc.).
     */
    public static function supportsTextTypes(): bool
    {
        return true;
    }

    /**
     * Whether this operator can be used on comparable columns (numeric, date, boolean, etc.).
     */
    public static function supportsComparableTypes(): bool
    {
        return true;
    }

    /**
     * Whether this operator can be used on JSON columns.
     */
    public static function supportsJsonTypes(): bool
    {
        return false;
    }

    /**
     * Execute a callback on a given column, providing the array of values.
     *
     * @param  Builder  $builder
     * @param  string  $column
     * @param  CategorizedValues  $values
     *
     * @throws ApiQueryBuilderException
     */
    abstract public function execute(Builder $builder, string $column, CategorizedValues $values): void;

    protected function appendRelations(Builder $builder, string $column, CategorizedValues $values, string $method = 'orWhereHas'): void
    {
        [$relationName, $relatedColumns] = explode('.', $column, 2);

        $builder->{$method}(Str::camel($relationName), function (Builder $builder) use ($relatedColumns, $values) {
            // Support for inner relation calls like model.relation.relation2.relation2_attribute
            if (str_contains($relatedColumns, '.')) {
                $this->appendRelations($builder, $relatedColumns, $values, 'whereHas');

                return;
            }

            $column = $this->qualifyColumnName($relatedColumns);
            $this->execute($builder, $column, $values);
            $this->checkExecuteForCustomfieldsParameter($builder);
        });
    }

    /**
     * Qualify column names with the current query model table to avoid ambiguity when joins are present.
     */
    protected function qualifyColumnName(string $column): string
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        $model = $this->builder->getModel();

        if ($model === null) {
            return $column;
        }

        return $this->builder->qualifyColumn($column);
    }

    /**
     * @param  Builder  $builder
     * @param  string  $column
     * @param  CategorizedValues  $values
     * @param  string  $operator
     *
     * @throws ApiQueryBuilderException
     */
    protected function lessOrMoreCallback(Builder $builder, string $column, CategorizedValues $values, string $operator)
    {
        $this->checkAllowedValues($values, $operator);

        if (count($values->and) > 1) {
            throw new InvalidOperatorUsageException("The '$operator' operator expects exactly one value, but multiple were provided.");
        }

        if (!$values->and) {
            throw new InvalidOperatorUsageException("No value was provided for the '$operator' operator.");
        }

        $method = $this->isDate($this->searchParser->type) ? 'whereDate' : 'where';
        $builder->{$method}($column, $operator, $values->and[0]);
    }

    /**
     * @param  Builder  $builder
     * @param  string  $column
     * @param  CategorizedValues  $values
     * @param  string  $operator
     *
     * @throws ApiQueryBuilderException
     */
    protected function betweenCallback(Builder $builder, string $column, CategorizedValues $values, string $operator)
    {
        $this->checkAllowedValues($values, $operator);

        if (count($values->and) != 2) {
            throw new InvalidOperatorUsageException("The '$operator' operator expects exactly 2 values, but " . count($values->and) . " were provided.");
        }

        $callback = $operator == '<>' ? 'whereBetween' : 'whereNotBetween';

        $builder->{$callback}($column, [$values->and[0], $values->and[1]]);
    }

    /**
     * @param  Builder  $builder
     * @param  string  $column
     * @param  CategorizedValues  $values
     * @param  string  $operator
     *
     * @throws ApiQueryBuilderException
     */
    protected function containsCallback(Builder $builder, string $column, CategorizedValues $values, string $operator)
    {
        if ($values->andLike) {
            $builder->where($column, $this->getLikeOperator(), '%' . $values->andLike[0] . '%');
        }

        if ($values->and) {
            $builder->where(function (Builder $q) use ($column, $values) {
                foreach (array_values($values->and) as $i => $andValue) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $q->{$method}($column, $this->getLikeOperator(), '%' . $andValue . '%');
                }
            });
        }
    }

    /**
     * @param  Builder  $builder
     * @param  string  $column
     * @param  CategorizedValues  $values
     * @param  string  $operator
     *
     * @throws ApiQueryBuilderException
     */
    protected function endsWithCallback(Builder $builder, string $column, CategorizedValues $values, string $operator)
    {
        if ($values->andLike) {
            $builder->where($column, $this->getLikeOperator(), '%' . $values->andLike[0]);
        }

        if ($values->and) {
            $builder->where(function (Builder $q) use ($column, $values) {
                foreach (array_values($values->and) as $i => $andValue) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $q->{$method}($column, $this->getLikeOperator(), '%' . $andValue);
                }
            });
        }
    }

    /**
     * @param  Builder  $builder
     * @param  string  $column
     * @param  CategorizedValues  $values
     * @param  string  $operator
     *
     * @throws ApiQueryBuilderException
     */
    protected function startsWithCallback(Builder $builder, string $column, CategorizedValues $values, string $operator)
    {
        if ($values->andLike) {
            $builder->where($column, $this->getLikeOperator(), $values->andLike[0] . '%');
        }

        if ($values->and) {
            $builder->where(function (Builder $q) use ($column, $values) {
                foreach (array_values($values->and) as $i => $andValue) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $q->{$method}($column, $this->getLikeOperator(), $andValue . '%');
                }
            });
        }
    }

    /**
     * Should throw exception if anything except '$values->and' is filled out.
     *
     * @param  CategorizedValues  $values
     * @param  string  $operator
     *
     * @throws ApiQueryBuilderException
     */
    protected function checkAllowedValues(CategorizedValues $values, string $operator): void
    {
        if ($values->null || $values->notNull || $values->not || $values->notLike || $values->andLike) {
            throw new InvalidOperatorUsageException("The '$operator' operator is not supported for text-type fields. Only comparable field types (numeric, date, etc.) are allowed.");
        }
    }

    protected function isDate(string $type): bool
    {
        return in_array($type, self::DATE_FIELDS);
    }

    //Hack to enable case-insensitive search when using PostgreSQL database
    protected function getLikeOperator(): string
    {
        if (DB::connection()->getPDO()->getAttribute(PDO::ATTR_DRIVER_NAME) == 'pgsql') {
            return 'ILIKE';
        }

        return 'LIKE';
    }

    protected function checkExecuteForCustomfieldsParameter($builder)
    {
        if ($this->searchParser instanceof CustomFieldSearchParser) {
            $builder->where($this->searchParser->cf_field_identificator, '=', $this->searchParser->cf_field_value);
        }
    }
}

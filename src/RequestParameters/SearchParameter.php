<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\RequestParameters;

use Illuminate\Database\Eloquent\Builder;
use PowerVending\LaravelApiQueryBuilder\{ApiQuery, CustomFieldSearchParser, SearchParser, SearchParserInterface};
use PowerVending\LaravelApiQueryBuilder\Config\OperatorsConfig;
use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;
use PowerVending\LaravelApiQueryBuilder\SearchCallbacks\AbstractCallback;

class SearchParameter extends AbstractParameter
{
    public const OR = '||';

    public const AND = '&&';

    public const AND_INCLUSIVE_CF = '&&_INC_CF';

    public const LARAVEL_WHERE = 'where';

    public const LARAVEL_OR_WHERE = 'orWhere';

    protected OperatorsConfig $operatorsConfig;

    public static function getParameterName(): string
    {
        return 'search';
    }

    /**
     * @throws ApiQueryBuilderException
     */
    protected function appendQuery(): void
    {
        $arguments = $this->arguments;

        $this->operatorsConfig = new OperatorsConfig();

        // Wrapped within a where clause to protect from orWhere "exploits".
        $this->builder->where(function (Builder $builder) use ($arguments) {
            $this->makeQuery($builder, $arguments);
        });
    }

    /**
     * Making query from input parameters with recursive calls if needed for top level logical operators (check readme).
     *
     * @param  Builder  $builder
     * @param  array  $arguments
     * @param  string  $boolOperator
     *
     * @throws ApiQueryBuilderException
     */
    protected function makeQuery(Builder $builder, array $arguments, string $boolOperator = self::AND): void
    {
        foreach ($arguments as $key => $value) {
            if ($this->isTopLevelBoolOperator($key)) {
                $this->makeQuery($builder, $value, $key);
                continue;
            }

            $functionName = $this->getQueryFunctionName($boolOperator);

            if ($this->isTopLevelInclusiveCFOperator($key)) {
                // Custom fields custom search logic ..... both columns has to be in the same where clause (custom_field_id & search column)
                $builder->{$functionName}(function ($queryBuilder) use ($value) {
                    $searchModel = new CustomFieldSearchParser($this->modelConfig, $this->operatorsConfig, $value);
                    $this->appendSingle($queryBuilder, $this->operatorsConfig, $searchModel);
                });
                continue;
            } elseif ($this->queryInitiatedByTopLevelBool($key, $value)) {
                $builder->{$functionName}(function ($queryBuilder) use ($value) {
                    // Recursion for inner keys which are &&/||
                    $this->makeQuery($queryBuilder, $value);
                });
                continue;
            }

            if ($this->hasSubSearch($key, $value)) {
                // If query has sub-search, it is a relation for sure.
                $normalizedRelation = $this->assertRelationExists($key);

                $builder->whereHas($normalizedRelation, function ($query) use ($value) {
                    $jsonQuery = new ApiQuery($query, $value);
                    $jsonQuery->search();
                });
                continue;
            }

            if ($this->isRelationColumnSearch($key, $value)) {
                [$relationPath] = $this->extractRelationAndColumn($key);
                $this->assertRelationExists($relationPath);
            }

            $this->makeSingleQuery($functionName, $builder, $key, $value);
        }
    }

    protected function isTopLevelBoolOperator($key): bool
    {
        return in_array($key, [self::OR, self::AND], true);
    }

    protected function isTopLevelInclusiveCFOperator($key): bool
    {
        return in_array($key, [self::AND_INCLUSIVE_CF], true);
    }

    /**
     * @param  string  $boolOperator
     * @return string
     *
     * @throws ApiQueryBuilderException
     */
    protected function getQueryFunctionName(string $boolOperator): string
    {
        if ($boolOperator === self::AND || $boolOperator === self::AND_INCLUSIVE_CF) {
            return self::LARAVEL_WHERE;
        } elseif ($boolOperator === self::OR) {
            return self::LARAVEL_OR_WHERE;
        }

        throw new ApiQueryBuilderException('Invalid bool operator provided');
    }

    protected function queryInitiatedByTopLevelBool($key, $value): bool
    {
        // Since this will be triggered by recursion, key will be numeric
        // and not the actual key.
        return !is_string($key) && is_array($value);
    }

    protected function hasSubSearch($key, $value): bool
    {
        return is_string($key) && is_array($value);
    }

    protected function isRelationColumnSearch($key, $value): bool
    {
        return is_string($key)
            && is_string($value)
            && str_contains($key, '.');
    }

    /**
     * @return array{string, string}
     *
     * @throws ApiQueryBuilderException
     */
    protected function extractRelationAndColumn(string $key): array
    {
        $parts = explode('.', $key);
        $column = array_pop($parts);
        $relationPath = implode('.', $parts);

        if ($relationPath === '' || $column === '') {
            throw new ApiQueryBuilderException("Invalid relation column search key '$key'.");
        }

        return [$relationPath, $column];
    }

    /**
     * @param  string  $functionName
     * @param  Builder  $builder
     * @param  $key
     * @param  $value
     *
     * @throws ApiQueryBuilderException
     */
    protected function makeSingleQuery(string $functionName, Builder $builder, $key, $value): void
    {
        $builder->{$functionName}(function ($queryBuilder) use ($key, $value) {
            $this->applyArguments($queryBuilder, $this->operatorsConfig, $key, $value);
        });
    }

    /**
     * @param  Builder  $builder
     * @param  OperatorsConfig  $operatorsConfig
     * @param  string  $column
     * @param  string  $argument
     *
     * @throws ApiQueryBuilderException
     */
    protected function applyArguments(Builder $builder, OperatorsConfig $operatorsConfig, string $column, string $argument): void
    {
        $splitArguments = $this->splitByBoolOperators($argument);

        foreach ($splitArguments as $splitArgument) {
            $builder->orWhere(function ($builder) use ($splitArgument, $operatorsConfig, $column) {
                foreach ($splitArgument as $argument) {
                    $searchModel = new SearchParser($this->modelConfig, $operatorsConfig, $column, $argument);

                    $this->appendSingle($builder, $operatorsConfig, $searchModel);
                }
            });
        }
    }

    /**
     * @param  $argument
     * @return array
     *
     * @throws ApiQueryBuilderException
     */
    protected function splitByBoolOperators($argument): array
    {
        $splitByOr = explode(self::OR, $argument);

        if (empty($splitByOr)) {
            throw new ApiQueryBuilderException('Something went wrong. Did you forget to add arguments?');
        }

        $splitByAnd = [];

        foreach ($splitByOr as $item) {
            $splitByAnd[] = explode(self::AND, $item);
        }

        return $splitByAnd;
    }

    /**
     * Append the query based on the given argument.
     *
     * @param  Builder  $builder
     * @param  OperatorsConfig  $operatorsConfig
     * @param  SearchParser  $searchParser
     *
     * @throws ApiQueryBuilderException
     */
    protected function appendSingle(Builder $builder, OperatorsConfig $operatorsConfig, SearchParserInterface $searchParser): void
    {
        $callbackClassName = $operatorsConfig->getCallbackClassFromOperator($searchParser->operator);

        /** @var AbstractCallback $callback */
        new $callbackClassName($builder, $searchParser);
    }
}

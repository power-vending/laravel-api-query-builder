<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder;

use Illuminate\Support\Facades\Config;
use PowerVending\LaravelApiQueryBuilder\Config\{ModelConfig, OperatorsConfig};
use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;
use PowerVending\LaravelApiQueryBuilder\Traits\CleansValues;

class SearchParser implements SearchParserInterface
{
    use CleansValues;

    /**
     * Constant by which values will be split within a single parameter. E.g. parameter=value1;value2.
     */
    public const VALUE_SEPARATOR = ';';

    public string $column;

    public array  $values;

    public string $type;

    public string $operator;

    private string      $argument;

    private ModelConfig $modelConfig;

    private bool $from_primary_key;

    /**
     * Search constructor.
     *
     * @param  ModelConfig  $modelConfig
     * @param  OperatorsConfig  $operatorsConfig
     * @param  string  $column
     * @param  string  $argument
     *
     * @throws ApiQueryBuilderException
     */
    public function __construct(ModelConfig $modelConfig, OperatorsConfig $operatorsConfig, string $column, string $argument)
    {
        $this->modelConfig = $modelConfig;
        $this->from_primary_key = $modelConfig->isPrimaryKey($column);
        $this->column = $this->from_primary_key ? $modelConfig->getPrimaryColumn() : $column;
        $this->argument = $argument;

        $this->checkForForbiddenColumns();

        $this->operator = $this->parseOperator($operatorsConfig->getOperators(), $argument);
        $arguments = str_replace($this->operator, '', $this->argument);
        $this->values = $this->splitValues($arguments);
        $this->type = $this->getColumnType();

        $this->validateOperatorForCastType();
    }

    /**
     * @return bool
     *
     * @throws ApiQueryBuilderException
     */
    public function isModelRelation(): bool
    {

        return str_contains($this->column, '.') && !$this->from_primary_key;
    }

    /**
     * @param  $operators
     * @param  string  $argument
     * @return string
     *
     * @throws ApiQueryBuilderException
     */
    protected function parseOperator($operators, string $argument): string
    {
        foreach ($operators as $operator) {
            $argumentHasOperator = strpos($argument, $operator) !== false;

            if (!$argumentHasOperator) {
                continue;
            }

            return $operator;
        }

        throw new ApiQueryBuilderException("No valid callback registered for $argument. Are you missing an operator?");
    }

    /**
     * Split values by a given separator.
     *
     * Input: val1;val2
     *
     * Output: val1
     *         val2
     *
     * @param  string  $values
     * @return array
     *
     * @throws ApiQueryBuilderException
     */
    protected function splitValues(string $values): array
    {
        $valueArray = explode(self::VALUE_SEPARATOR, $values);
        $cleanedUpValues = $this->cleanValues($valueArray);

        if (count($cleanedUpValues) < 1) {
            throw new ApiQueryBuilderException("Column '$this->column' is missing a value.");
        }

        return $cleanedUpValues;
    }

    /**
     * @return string
     *
     * @throws ApiQueryBuilderException
     */
    protected function getColumnType(): string
    {
        $castType = $this->modelConfig->getTypeFromCast($this->column);

        if ($castType !== null) {
            return $castType;
        }

        $columns = $this->modelConfig->getModelColumns();

        if (!array_key_exists($this->column, $columns)) {
            if ($this->isModelRelation()) {
                $parts = explode('.', $this->column);
                $columnName = array_pop($parts);

                try {
                    $model = $this->modelConfig->getModel();

                    foreach ($parts as $part) {
                        if (method_exists($model, $part)) {
                            $model = $model->{$part}()->getRelated();
                        } else {
                            return 'generic';
                        }
                    }

                    $relatedConfig = new ModelConfig($model);
                    $relatedCastType = $relatedConfig->getTypeFromCast($columnName);

                    if ($relatedCastType !== null) {
                        return $relatedCastType;
                    }

                    $relatedColumns = $relatedConfig->getModelColumns();

                    if (array_key_exists($columnName, $relatedColumns)) {
                        return $relatedColumns[$columnName];
                    }
                } catch (\Exception $e) {
                    return 'generic';
                }
            }

            return 'generic';
        }

        return $columns[$this->column];
    }

    /**
     * Validate that the parsed operator is allowed for the resolved cast type,
     * when 'cast_operators' restricts operators for that type.
     *
     * @throws ApiQueryBuilderException
     */
    protected function validateOperatorForCastType(): void
    {
        $normalized_type = strtolower(trim($this->type));
        $cast_operators = Config::get('api-query-builder.cast_operators', []);

        if (!array_key_exists($normalized_type, $cast_operators)) {
            return;
        }

        $allowed_operators = array_map(
            fn ($class) => $class::operator(),
            $cast_operators[$normalized_type]
        );

        if (!in_array($this->operator, $allowed_operators, true)) {
            throw new ApiQueryBuilderException(
                "Operator '$this->operator' is not allowed for cast type '$this->type' on column '$this->column'."
            );
        }
    }

    /**
     * Check if global forbidden key is used.
     *
     * @throws ApiQueryBuilderException
     */
    protected function checkForForbiddenColumns()
    {
        $forbiddenKeys = Config::get('api-query-builder.global_forbidden_columns');
        $forbiddenKeys = $this->modelConfig->getForbidden($forbiddenKeys);

        if (in_array($this->column, $forbiddenKeys)) {
            throw new ApiQueryBuilderException("Searching by '$this->column' field is forbidden. Check the configuration if this is not a desirable behavior.");
        }
    }
}

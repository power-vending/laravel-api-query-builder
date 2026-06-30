<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder;

use Illuminate\Support\Facades\Config;
use PowerVending\LaravelApiQueryBuilder\Config\{ModelConfig, OperatorsConfig};
use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;
use PowerVending\LaravelApiQueryBuilder\Traits\CleansValues;

class CustomFieldSearchParser implements SearchParserInterface
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

    public string $cf_field_identificator = 'custom_field_id';

    public string $cf_field_value = '';

    private string $argument;

    private ModelConfig $modelConfig;

    /**
     * @param  ModelConfig  $modelConfig
     * @param  OperatorsConfig  $operatorsConfig
     * @param  array  $arguments
     *
     * @throws ApiQueryBuilderException
     */
    public function __construct(ModelConfig $modelConfig, OperatorsConfig $operatorsConfig, array $arguments)
    {
        $this->modelConfig = $modelConfig;

        foreach ($arguments as $col => $val) {
            if (str_contains($col, $this->cf_field_identificator)) {
                $this->cf_field_value = $val;
            } else {
                $this->column = $col;
                $this->argument = $val;
            }
        }

        $this->checkForForbiddenColumns();

        $this->operator = $this->parseOperator($operatorsConfig->getOperators(), $this->argument);
        $arguments = str_replace($this->operator, '', $this->argument);
        $this->values = $this->splitValues($arguments);
        $this->type = $this->getColumnType();
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function isModelRelation(): bool
    {
        return false;
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
            // TODO: integrate recursive column check for related models?
            return 'generic';
        }

        return $columns[$this->column];
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

<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\RequestParameters;

use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;
use PowerVending\LaravelApiQueryBuilder\SQLProviders\SQLFunctions;

class ExceptsParameter extends AbstractParameter
{
    public static function getParameterName(): string
    {
        return 'excepts';
    }

    protected function areArgumentsValid(): void
    {
        parent::areArgumentsValid();

        foreach ($this->arguments as $argument) {
            SQLFunctions::validateArgument($argument);
        }
    }

    protected function appendQuery(): void
    {
        $availableColumns = $this->resolveAvailableColumns();
        $selectedColumns = array_values(array_diff($availableColumns, $this->arguments));

        if (count($selectedColumns) < 1) {
            throw new ApiQueryBuilderException(
                "Parameter '{$this->getParameterName()}' removed all selectable columns."
            );
        }

        $this->builder->addSelect($selectedColumns);
    }

    /**
     * @throws ApiQueryBuilderException
     */
    protected function resolveAvailableColumns(): array
    {
        $configuredColumns = $this->modelConfig->getReturns();

        if ($configuredColumns !== ['*']) {
            return $configuredColumns;
        }

        $columns = array_keys($this->modelConfig->getModelColumns());

        if (count($columns) < 1) {
            throw new ApiQueryBuilderException(
                "Parameter '{$this->getParameterName()}' could not resolve selectable columns. Configure model 'returns' or make schema columns available."
            );
        }

        return $columns;
    }
}

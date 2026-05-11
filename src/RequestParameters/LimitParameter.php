<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\RequestParameters;

use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;

class LimitParameter extends AbstractParameter
{
    public static function getParameterName(): string
    {
        return 'limit';
    }

    protected function areArgumentsValid(): void
    {
        if (count($this->arguments) != 1) {
            throw new ApiQueryBuilderException("Parameter '{$this->getParameterName()}' expects only one argument.");
        }

        if (!is_numeric($this->arguments[0])) {
            throw new ApiQueryBuilderException("Parameter '{$this->getParameterName()}' must be numeric.");
        }
    }

    protected function appendQuery(): void
    {
        $this->builder->limit($this->arguments[0]);
    }
}

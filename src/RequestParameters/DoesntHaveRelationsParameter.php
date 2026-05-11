<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\RequestParameters;

use Illuminate\Support\Str;
use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;

class DoesntHaveRelationsParameter extends AbstractParameter
{
    public static function getParameterName(): string
    {
        return 'doesnt_have_relations';
    }

    protected function appendQuery(): void
    {
        foreach ($this->arguments as $argument) {
            if (is_string($argument)) {
                $this->builder->doesntHave(Str::camel($argument));
                continue;
            }

            throw new ApiQueryBuilderException('Wrong relation parameters provided.');
        }
    }
}

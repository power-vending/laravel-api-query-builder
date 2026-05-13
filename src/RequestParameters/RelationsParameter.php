<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\RequestParameters;

use PowerVending\LaravelApiQueryBuilder\ApiQuery;
use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;

class RelationsParameter extends AbstractParameter
{
    public static function getParameterName(): string
    {
        return 'relations';
    }

    protected function appendQuery(): void
    {
        foreach ($this->arguments as $argument) {
            if (is_string($argument)) {
                $this->appendSimpleRelation($argument);
                continue;
            }

            if (is_array($argument) && count($argument) > 0) {
                $this->appendComplexRelation($argument);
                continue;
            }

            throw new ApiQueryBuilderException('Wrong relation parameters provided.');
        }
    }

    protected function appendSimpleRelation(string $argument): void
    {
        $normalizedRelation = $this->assertRelationExists($argument);

        $this->builder->with($normalizedRelation);
    }

    protected function appendComplexRelation(array $argument): void
    {
        $relation = key($argument);
        $input = $argument[$relation];
        $normalizedRelation = $this->assertRelationExists($relation);

        $this->builder->with([$normalizedRelation => function ($query) use ($input) {
            $jsonQuery = new ApiQuery($query->getQuery(), $input);
            $jsonQuery->search();
        }]);
    }
}

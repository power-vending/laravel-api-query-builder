<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\RequestParameters;

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
                $normalizedRelation = $this->assertRelationExists($argument);

                $this->builder->doesntHave($normalizedRelation);
                continue;
            }

            throw new ApiQueryBuilderException('Wrong relation parameters provided.');
        }
    }
}

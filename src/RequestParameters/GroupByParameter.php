<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\RequestParameters;

use PowerVending\LaravelApiQueryBuilder\Support\ColumnQualifier;

class GroupByParameter extends AbstractParameter
{
    public static function getParameterName(): string
    {
        return 'group_by';
    }

    protected function appendQuery(): void
    {
        $columns = array_map(
            fn (mixed $column) => ColumnQualifier::qualify($this->builder, (string) $column),
            $this->arguments
        );

        $this->builder->groupBy($columns);
    }
}

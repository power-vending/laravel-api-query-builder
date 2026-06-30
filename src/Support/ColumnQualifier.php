<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Support;

use Illuminate\Database\Eloquent\Builder;

class ColumnQualifier
{
    public static function qualify(Builder $builder, string $column): string
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        $model = $builder->getModel();

        if ($model === null) {
            return $column;
        }

        return $builder->qualifyColumn($column);
    }

    public static function qualifyForSelect(mixed $column, string $parentTable): mixed
    {
        if (!is_string($column) || !self::isSimpleSelectColumn($column)) {
            return $column;
        }

        return "{$parentTable}.{$column}";
    }

    public static function isSimpleSelectColumn(string $column): bool
    {
        if (str_contains($column, '*')) {
            return false;
        }

        return preg_match('/^"?[a-zA-Z_][a-zA-Z0-9_]*"?$/', $column) === 1;
    }
}

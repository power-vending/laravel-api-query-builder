<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;

class ColumnQualifier
{
    /**
     * Prefix a column with the table (or alias) the given builder selects from.
     *
     * Columns that already carry a prefix are returned untouched.
     */
    public static function qualify(Builder $builder, string $column): string
    {
        if (self::isQualified($column)) {
            return $column;
        }

        $prefix = self::tableAlias($builder);

        if ($prefix === null) {
            return $column;
        }

        return "{$prefix}.{$column}";
    }

    /**
     * Resolve the table name (or alias) the builder is currently selecting from.
     *
     * The query 'from' is preferred over the model table because Eloquent aliases
     * the table on self-referencing relation sub-queries (e.g. 'users as laravel_reserved_0').
     */
    public static function tableAlias(Builder $builder): ?string
    {
        $from = $builder->getQuery()->from ?? null;

        if (is_string($from) && trim($from) !== '') {
            return self::stripAlias($from);
        }

        $model = $builder->getModel();

        if ($model === null) {
            return null;
        }

        $table = $model->getTable();

        return $table === '' ? null : self::stripAlias($table);
    }

    /**
     * Every table name/alias reachable by the current query: the 'from' plus every join.
     *
     * @return array<int, string>
     */
    public static function knownTableAliases(Builder $builder): array
    {
        $aliases = [];
        $from = self::tableAlias($builder);

        if ($from !== null) {
            $aliases[] = $from;
        }

        foreach ($builder->getQuery()->joins ?? [] as $join) {
            $table = $join->table ?? null;

            if (!is_string($table) || trim($table) === '') {
                continue;
            }

            $aliases[] = self::stripAlias($table);
        }

        return array_values(array_unique($aliases));
    }

    /**
     * Whether the column is already prefixed with a table reachable by the query.
     */
    public static function isQualifiedWithKnownTable(Builder $builder, string $column): bool
    {
        if (!self::isQualified($column)) {
            return false;
        }

        $prefix = self::unwrapIdentifier(substr($column, 0, strrpos($column, '.')));

        foreach (self::knownTableAliases($builder) as $alias) {
            if (strcasecmp($alias, $prefix) === 0) {
                return true;
            }

            // Schema-qualified 'from' (e.g. 'public.companies') still matches 'companies.column'.
            if (str_contains($alias, '.') && strcasecmp(substr($alias, strrpos($alias, '.') + 1), $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    public static function isQualified(string $column): bool
    {
        return str_contains($column, '.');
    }

    public static function qualifyForSelect(mixed $column, string $parentTable): mixed
    {
        if ($column instanceof Expression || !is_string($column) || !self::isSimpleSelectColumn($column)) {
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

    /**
     * Strip an ' as alias' suffix and identifier quoting from a table reference.
     */
    protected static function stripAlias(string $table): string
    {
        $table = trim($table);

        if (preg_match('/^(.*)\s+as\s+(.+)$/i', $table, $matches) === 1) {
            $table = $matches[2];
        }

        return self::unwrapIdentifier($table);
    }

    protected static function unwrapIdentifier(string $identifier): string
    {
        return trim(trim($identifier), "`\"[]' ");
    }
}

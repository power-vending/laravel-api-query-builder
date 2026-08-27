<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Query builder that auto-qualifies bare columns in where clauses with the base
 * table, but only when they are genuinely ambiguous - that is, the query has
 * joins AND the column exists both on the base table and on at least one joined
 * table (e.g. 'id', present in `terminals` and in a joined `companies`).
 *
 * Prefixing every bare column with the base table, regardless of whether it
 * exists there, is unsafe: a column that only exists on a joined table (e.g. a
 * pivot's own foreign key, such as 'configuration_id' in
 * `companies_has_configurations`) would be rewritten into a column that does not
 * exist on the base table, breaking a query that was never ambiguous.
 *
 * This removes the need for callers to write `->whereNotIn('terminals.id', ...)`
 * by hand after requestQuery()/requestPaginate() - any genuinely ambiguous bare
 * column is resolved against the model table automatically.
 *
 * Nested closures are handled in addNestedWhereQuery(), the single point every
 * closure - Query\Builder's and Eloquent's - goes through before its wheres are
 * merged into this query.
 */
class QualifyingQueryBuilder extends Builder
{
    /**
     * Per-connection cache of table => [column => bool], so we don't hit the
     * information schema on every where() call.
     *
     * @var array<string, array<string, array<string, bool>>>
     */
    protected static array $columnExistsCache = [];

    /**
     * Drop the cached column lookups. Mostly useful in tests, where the schema
     * is rebuilt between cases.
     */
    public static function flushColumnCache(): void
    {
        static::$columnExistsCache = [];
    }

    /**
     * Wheres added before any join exists (e.g. Terminal::where('id', $id)
     * called before ->requestQuery()) stay bare until a join shows up. When it
     * does, we sweep the wheres already on the query and qualify the ones that
     * became ambiguous - otherwise only the wheres added *after* the join would
     * get the call-time treatment.
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        parent::join($table, $first, $operator, $second, $type, $where);

        $this->qualifyExistingWheres();

        return $this;
    }

    /**
     * A nested closure builds its wheres on a query of its own, which carries no
     * joins, so nothing in it can decide on ambiguity by itself. This is where
     * every closure - Query\Builder's and Eloquent's alike - hands its wheres
     * over, so qualify them here, against this query's joins.
     */
    public function addNestedWhereQuery($query, $boolean = 'and')
    {
        if ($query instanceof Builder) {
            $this->qualifyWheres($query->wheres);
        }

        return parent::addNestedWhereQuery($query, $boolean);
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        return parent::where($this->qualifyIfNeeded($column), $operator, $value, $boolean);
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        return parent::whereIn($this->qualifyIfNeeded($column), $values, $boolean, $not);
    }

    public function whereNotIn($column, $values, $boolean = 'and')
    {
        return parent::whereNotIn($this->qualifyIfNeeded($column), $values, $boolean);
    }

    public function whereNull($columns, $boolean = 'and', $not = false)
    {
        return parent::whereNull($this->qualifyColumnsIfNeeded($columns), $boolean, $not);
    }

    public function whereNotNull($columns, $boolean = 'and')
    {
        return parent::whereNotNull($this->qualifyColumnsIfNeeded($columns), $boolean);
    }

    /**
     * Qualify a single column argument, leaving untouched anything that is not a
     * genuinely ambiguous bare column (arrays, Closures, Expressions, already
     * qualified strings, columns missing from the base table, columns that exist
     * on the base table but on no other).
     */
    protected function qualifyIfNeeded(mixed $column): mixed
    {
        if (!is_string($column) || str_contains($column, '.') || empty($this->joins)) {
            return $column;
        }

        if (!$this->isAmbiguous($column)) {
            return $column;
        }

        return $this->fromPrefix() . ".{$column}";
    }

    /**
     * Same as qualifyIfNeeded(), but for whereNull/whereNotNull, which accept
     * either a single column or an array of columns.
     */
    protected function qualifyColumnsIfNeeded(mixed $columns): mixed
    {
        if (is_array($columns)) {
            return array_map(fn ($column) => $this->qualifyIfNeeded($column), $columns);
        }

        return $this->qualifyIfNeeded($columns);
    }

    /**
     * Retroactively qualify the bare 'column' entries already sitting in
     * $wheres, now that a join may have made them ambiguous. Idempotent:
     * already qualified columns (with a dot) are left untouched, and only
     * genuinely ambiguous columns are rewritten.
     */
    protected function qualifyExistingWheres(): void
    {
        if (!is_string($this->from) || empty($this->wheres)) {
            return;
        }

        $this->qualifyWheres($this->wheres);
    }

    /**
     * @param  array<int, mixed>  $wheres
     */
    protected function qualifyWheres(array &$wheres): void
    {
        foreach ($wheres as &$where) {
            // Nested closures carry no joins of their own, so ambiguity is still
            // decided against this query's joins.
            if (($where['type'] ?? null) === 'Nested' && ($where['query'] ?? null) instanceof Builder) {
                $this->qualifyWheres($where['query']->wheres);
                continue;
            }

            if (
                isset($where['column'])
                && is_string($where['column'])
                && !str_contains($where['column'], '.')
                && $this->isAmbiguous($where['column'])
            ) {
                $where['column'] = $this->fromPrefix() . ".{$where['column']}";
            }
        }

        unset($where);
    }

    /**
     * Whether a bare column exists on the base table AND on at least one joined
     * table - i.e. whether the database would actually find it ambiguous.
     */
    protected function isAmbiguous(string $column): bool
    {
        $baseTable = $this->fromTable();

        if ($baseTable === null || !$this->columnExistsOn($baseTable, $column)) {
            return false;
        }

        foreach ($this->joins ?? [] as $join) {
            $joinedTable = $this->tableReference($join->table ?? null)['table'] ?? null;

            if ($joinedTable !== null && $joinedTable !== $baseTable && $this->columnExistsOn($joinedTable, $column)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Real base table name, used for schema lookups.
     */
    protected function fromTable(): ?string
    {
        return $this->tableReference($this->from)['table'];
    }

    /**
     * Prefix to write in front of a column: the base table alias when the query
     * uses one, the table name otherwise.
     */
    protected function fromPrefix(): string
    {
        $reference = $this->tableReference($this->from);

        return $reference['alias'] ?? $reference['table'] ?? '';
    }

    /**
     * Split a 'table as alias' reference into its parts. Non-string references
     * (sub-queries, expressions) resolve to nulls.
     *
     * @return array{table: string|null, alias: string|null}
     */
    protected function tableReference(mixed $reference): array
    {
        if (!is_string($reference) || trim($reference) === '') {
            return ['table' => null, 'alias' => null];
        }

        $reference = trim($reference);

        if (preg_match('/^(.*)\s+as\s+(.+)$/i', $reference, $matches) === 1) {
            return [
                'table' => $this->unwrapIdentifier($matches[1]),
                'alias' => $this->unwrapIdentifier($matches[2]),
            ];
        }

        return ['table' => $this->unwrapIdentifier($reference), 'alias' => null];
    }

    protected function unwrapIdentifier(string $identifier): string
    {
        return trim(trim($identifier), "`\"[]' ");
    }

    protected function columnExistsOn(string $table, string $column): bool
    {
        $connection = $this->getConnection();
        $cacheKey = $connection->getName() ?? '';

        if (!isset(static::$columnExistsCache[$cacheKey][$table][$column])) {
            static::$columnExistsCache[$cacheKey][$table][$column] =
                Schema::connection($connection->getName())->hasColumn($table, $column);
        }

        return static::$columnExistsCache[$cacheKey][$table][$column];
    }
}

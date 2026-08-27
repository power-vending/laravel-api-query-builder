<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\RequestParameters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, HasOneOrMany, MorphMany, MorphOneOrMany, MorphTo, MorphToMany, Relation};
use Illuminate\Support\Str;
use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;
use PowerVending\LaravelApiQueryBuilder\Support\ColumnQualifier;

class OrderByParameter extends AbstractParameter
{
    protected array $joinedRelations = [];

    protected array $joinedPivots = [];

    /**
     * Table names/aliases already taken by the query, so every join we add gets a unique one.
     *
     * @var array<int, string>|null
     */
    protected ?array $usedAliases = null;

    protected bool $didGroupByParentPk = false;

    public static function getParameterName(): string
    {
        return 'order_by';
    }

    protected function appendQuery(): void
    {
        foreach ($this->arguments as $column => $direction) {
            [$column, $direction] = $this->fallBackToDefaultDirection($column, $direction);

            $this->appendSingle($column, $direction);
        }
    }

    /**
     * If argument is provided as a simple string without direction, we will
     * assume that direction is 'asc'.
     *
     * @param  string|int  $column
     * @param  string  $direction
     * @return array
     */
    protected function fallBackToDefaultDirection(string|int $column, string $direction): array
    {
        if (is_numeric($column)) {
            $column = $direction;
            $direction = 'asc';
        }

        return [$column, $direction];
    }

    protected function appendSingle(string $column, string $direction): void
    {
        // Check if column contains a dot (relation.column or relation.relation.column...)
        if (str_contains($column, '.')) {
            // Already prefixed with the parent table or with a table joined by the query.
            if (ColumnQualifier::isQualifiedWithKnownTable($this->builder, $column)) {
                $this->builder->orderBy($column, $direction);
                return;
            }

            $this->appendRelationOrder($column, $direction);
            return;
        }

        $this->builder->orderBy(ColumnQualifier::qualify($this->builder, $column), $direction);
    }

    /**
     * Handle ordering by a related column.
     *
     * @param  string  $column  The column in format "relation.column" or "relation.relation.column"
     * @param  string  $direction  The sort direction
     * @return void
     */
    protected function appendRelationOrder(string $column, string $direction): void
    {
        $parts = array_values(array_filter(explode('.', $column), fn ($p) => $p !== ''));

        if (count($parts) < 2) {
            $this->builder->orderBy(ColumnQualifier::qualify($this->builder, $column), $direction);
            return;
        }

        $orderColumn = array_pop($parts);
        $relationParts = $parts;

        $parentModel = $this->builder->getModel();
        $parentAlias = $this->parentAlias();

        $needsGroupBy = false;

        foreach ($relationParts as $index => $relationPart) {
            $relationMethod = Str::camel($relationPart);

            $relation = $this->resolveRelationOrFail($parentModel, $relationMethod, $column);

            $pathKey = $this->relationPathKey($relationParts, $index);
            $relatedAlias = $this->joinedRelations[$pathKey] ?? null;

            if (!$relatedAlias) {
                $relatedAlias = $this->reserveAlias($this->relationTable($relation));
                $this->joinRelationInternal($relation, $parentAlias, $relatedAlias, $pathKey);
                $this->joinedRelations[$pathKey] = $relatedAlias;
            }

            if ($this->isToManyRelation($relation)) {
                $needsGroupBy = true;
            }

            $parentAlias = $relatedAlias;
            $parentModel = $relation->getRelated();
        }

        $this->ensureSelectParentTable();

        if ($needsGroupBy) {
            $this->ensureGroupByParentPrimaryKey();
        }

        $this->builder->orderBy("$parentAlias.$orderColumn", $direction);
    }

    /**
     * Join a relation table to the query.
     *
     * @param  string  $relationName
     * @return void
     */
    protected function joinRelation(string $relationName): void
    {
        // Backwards-compat stub (kept to avoid breaking external overrides).
        $model = $this->builder->getModel();
        $relation = $this->resolveRelationOrFail($model, Str::camel($relationName), $relationName);
        $pathKey = $relationName;
        $relatedAlias = $this->reserveAlias($this->relationTable($relation));
        $this->joinRelationInternal($relation, $this->parentAlias(), $relatedAlias, $pathKey);
    }

    /**
     * Join a relation by its Relation object, allowing deep chains.
     */
    protected function joinRelationInternal(Relation $relation, string $parentAlias, string $relatedAlias, string $pathKey): void
    {
        $this->ensureSelectParentTable();

        if ($relation instanceof MorphTo) {
            throw new ApiQueryBuilderException('Ordering by a MorphTo relation is not supported, since it may point to several tables.');
        }

        $relatedTable = $relation->getRelated()->getTable();
        $relatedFrom = $this->aliasedTable($relatedTable, $relatedAlias);

        if ($relation instanceof BelongsTo) {
            $foreignKey = $relation->getForeignKeyName();
            $ownerKey = $relation->getOwnerKeyName();

            $this->builder->leftJoin(
                $relatedFrom,
                "$parentAlias.$foreignKey",
                '=',
                "$relatedAlias.$ownerKey"
            );

            return;
        }

        if ($relation instanceof HasOneOrMany) {
            $foreignKey = $relation->getForeignKeyName();
            $localKey = $relation->getLocalKeyName();

            $this->builder->leftJoin(
                $relatedFrom,
                function (JoinClause $join) use ($relation, $relatedAlias, $parentAlias, $foreignKey, $localKey) {
                    $join->on("$relatedAlias.$foreignKey", '=', "$parentAlias.$localKey");

                    if ($relation instanceof MorphOneOrMany) {
                        $join->where("$relatedAlias." . $relation->getMorphType(), '=', $relation->getMorphClass());
                    }
                }
            );

            return;
        }

        if ($relation instanceof BelongsToMany) {
            // Join pivot table first, then the related table.
            if (!isset($this->joinedPivots[$pathKey])) {
                $pivotTable = $relation->getTable();
                $pivotAlias = $this->reserveAlias($pivotTable);
                $pivotFrom = $this->aliasedTable($pivotTable, $pivotAlias);

                $parentKey = $relation->getParentKeyName();
                $foreignPivotKey = $relation->getForeignPivotKeyName();

                $this->builder->leftJoin(
                    $pivotFrom,
                    function (JoinClause $join) use ($relation, $pivotAlias, $parentAlias, $foreignPivotKey, $parentKey) {
                        $join->on("$pivotAlias.$foreignPivotKey", '=', "$parentAlias.$parentKey");

                        if ($relation instanceof MorphToMany) {
                            $join->where("$pivotAlias." . $relation->getMorphType(), '=', $relation->getMorphClass());
                        }
                    }
                );

                $this->joinedPivots[$pathKey] = $pivotAlias;
            }

            $relatedKey = $relation->getRelatedKeyName();
            $relatedPivotKey = $relation->getRelatedPivotKeyName();

            $this->builder->leftJoin(
                $relatedFrom,
                "$relatedAlias.$relatedKey",
                '=',
                $this->joinedPivots[$pathKey] . ".$relatedPivotKey"
            );

            return;
        }

        // If you need more relation types, implement them here.
        throw new ApiQueryBuilderException('Unsupported relation type for order_by.');
    }

    /**
     * Whether the relation may return several related rows, requiring a group by on the parent.
     */
    protected function isToManyRelation(Relation $relation): bool
    {
        return $relation instanceof HasMany
            || $relation instanceof MorphMany
            || $relation instanceof BelongsToMany;
    }

    /**
     * Resolve a relation method into a Relation instance or throw an ApiQueryBuilderException.
     */
    protected function resolveRelationOrFail(Model $model, string $relationMethod, string $originalColumn): Relation
    {
        if (!method_exists($model, $relationMethod)) {
            throw new ApiQueryBuilderException("Relation '$relationMethod' does not exist while ordering by '$originalColumn'.");
        }

        try {
            $relation = $model->$relationMethod();
        } catch (\Throwable $e) {
            throw new ApiQueryBuilderException(
                "Relation '$relationMethod' could not be resolved while ordering by '$originalColumn'.",
                0,
                $e
            );
        }

        if (!$relation instanceof Relation) {
            throw new ApiQueryBuilderException("Method '$relationMethod' is not a valid Eloquent relation while ordering by '$originalColumn'.");
        }

        return $relation;
    }

    protected function relationPathKey(array $relationParts, int $index): string
    {
        $slice = array_slice($relationParts, 0, $index + 1);
        $slice = array_map(fn ($p) => Str::camel($p), $slice);
        return implode('.', $slice);
    }

    protected function relationTable(Relation $relation): string
    {
        return $relation->getRelated()->getTable();
    }

    /**
     * Table name (or alias) the parent query selects from.
     */
    protected function parentAlias(): string
    {
        return ColumnQualifier::tableAlias($this->builder) ?? $this->builder->getModel()->getTable();
    }

    /**
     * Reserve a unique table alias so the same table can be joined more than once
     * (self relations, or two relations pointing to the same table) without ambiguity.
     */
    protected function reserveAlias(string $table): string
    {
        if ($this->usedAliases === null) {
            $this->usedAliases = array_map('strtolower', ColumnQualifier::knownTableAliases($this->builder));
        }

        $alias = $table;
        $suffix = 1;

        while (in_array(strtolower($alias), $this->usedAliases, true)) {
            $alias = $table . '_' . (++$suffix);
        }

        $this->usedAliases[] = strtolower($alias);

        return $alias;
    }

    /**
     * Build the 'from' fragment for a join, aliasing it only when needed.
     */
    protected function aliasedTable(string $table, string $alias): string
    {
        return $alias === $table ? $table : "$table as $alias";
    }

    /**
     * Ensure that we select parent table columns when joining.
     *
     * @return void
     */
    protected function ensureSelectParentTable(): void
    {
        $query = $this->builder->getQuery();
        $parentTable = $this->parentAlias();

        // If no columns are selected yet, select all from parent table
        if (is_null($query->columns)) {
            $this->builder->select("$parentTable.*");
            return;
        }

        // If columns are already selected, we need to qualify them to avoid ambiguity
        $qualifiedColumns = [];

        foreach ($query->columns as $column) {
            $qualifiedColumns[] = ColumnQualifier::qualifyForSelect($column, $parentTable);
        }

        // Replace the columns with qualified versions
        $query->columns = $qualifiedColumns;
    }

    protected function ensureGroupByParentPrimaryKey(): void
    {
        if ($this->didGroupByParentPk) {
            return;
        }

        $parentModel = $this->builder->getModel();
        $parentTable = $this->parentAlias();
        $parentPk = $parentModel->getKeyName();

        $this->builder->groupBy("$parentTable.$parentPk");
        $this->didGroupByParentPk = true;
    }
}

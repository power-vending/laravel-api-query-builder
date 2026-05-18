<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Schema;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\{Config, Schema};
use PowerVending\LaravelApiQueryBuilder\CategorizedValues;
use PowerVending\LaravelApiQueryBuilder\Config\{ModelConfig, OperatorsConfig};
use PowerVending\LaravelApiQueryBuilder\Exceptions\InvalidRelationException;
use PowerVending\LaravelApiQueryBuilder\Support\AllowedRelationModel;
use PowerVending\LaravelApiQueryBuilder\Support\RelationMethodNormalizer;

class QueryBuilderSchema
{
    /**
     * Generate schema for a model's searchable columns.
     *
     * @param Model|string $model
     * @param array|null $relations null = all configured, [] = none, ['rel1', 'rel.nested'] = specific
     * @return array
     */
    public static function forModel(Model|string $model, ?array $relations = null): array
    {
        if (is_string($model)) {
            $model = app($model);
        }

        $model_config = new ModelConfig($model);

        $relation_paths = $relations ?? $model_config->getRelations();
        $relations_schema = self::buildRelationsSchema($model, $relation_paths);

        return [
            'model' => get_class($model),
            'table' => $model->getTable(),
            'searchable_columns' => self::getSearchableColumns($model, $model_config),
            'sortable_columns' => self::getSortableColumns($model, $model_config),
            'relations' => $relations_schema,
        ];
    }

    /**
     * Generate schema for a related model (no nested relations to avoid recursion).
     */
    private static function forRelatedModel(Model $model): array
    {
        $model_config = new ModelConfig($model);

        return [
            'model' => get_class($model),
            'table' => $model->getTable(),
            'searchable_columns' => self::getSearchableColumns($model, $model_config),
            'sortable_columns' => self::getSortableColumns($model, $model_config),
            'relations' => [],
        ];
    }

    /**
     * Build a nested relation schema tree from relation paths.
     */
    private static function buildRelationsSchema(Model $model, array $relation_paths): array
    {
        $relations_schema = [];

        foreach ($relation_paths as $path) {
            if (!is_string($path) || trim($path) === '') {
                continue;
            }

            self::appendRelationPath($model, $relations_schema, $path);
        }

        return $relations_schema;
    }

    /**
     * Append one dot-notation relation path (e.g. "configurations.configuration")
     * into the nested relations tree.
     */
    private static function appendRelationPath(Model $model, array &$relations_schema, string $path): void
    {
        $segments = array_values(array_filter(explode('.', $path), fn ($segment) => $segment !== ''));

        if ($segments === []) {
            return;
        }

        $current_model = $model;
        $current_level = &$relations_schema;

        foreach ($segments as $segment) {
            $related_model = self::resolveSingleRelation($current_model, $segment);

            if ($related_model === null) {
                $model_class = get_class($current_model);

                throw new InvalidRelationException(
                    "Relation '$segment' does not exist on model '$model_class'."
                );
            }

            if (!isset($current_level[$segment])) {
                $current_level[$segment] = self::forRelatedModel($related_model);
            }

            if (!isset($current_level[$segment]['relations']) || !is_array($current_level[$segment]['relations'])) {
                $current_level[$segment]['relations'] = [];
            }

            $current_model = $related_model;
            $current_level = &$current_level[$segment]['relations'];
        }
    }

    /**
     * Resolve one relation segment on a model and return related model instance.
     */
    private static function resolveSingleRelation(Model $model, string $segment): ?Model
    {
        $method = RelationMethodNormalizer::normalize($segment);

        if ($method === null || !method_exists($model, $method)) {
            return null;
        }

        try {
            $relation = $model->{$method}();
        } catch (\Throwable) {
            return null;
        }

        if (!$relation instanceof Relation) {
            return null;
        }

        $related = $relation->getRelated();

        return AllowedRelationModel::isAllowed($related) ? $related : null;
    }

    private static function getSearchableColumns(Model $model, ModelConfig $model_config): array
    {
        $columns = [];
        $table = $model->getTable();
        $forbidden_columns = self::getForbiddenColumns($model, $model_config);

        if (!Schema::hasTable($table)) {
            return $columns;
        }

        $table_columns = Schema::getColumns($table);

        foreach ($table_columns as $column) {
            $column_name = $column['name'];

            if (in_array($column_name, $forbidden_columns)) {
                continue;
            }

            $type = $model_config->getTypeFromCast($column_name) ?? $column['type'];
            $operators = self::getOperatorsForType($type);

            $columns[$column_name] = [
                'type' => $type,
                'operators' => $operators,
                'nullable' => $column['nullable'] ?? false,
            ];
        }

        return $columns;
    }

    private static function getSortableColumns(Model $model, ModelConfig $model_config): array
    {
        $sortable = [];
        $table = $model->getTable();
        $forbidden_columns = self::getForbiddenColumns($model, $model_config);

        if (!Schema::hasTable($table)) {
            return $sortable;
        }

        $table_columns = Schema::getColumns($table);

        foreach ($table_columns as $column) {
            if (!in_array($column['name'], $forbidden_columns)) {
                $sortable[] = $column['name'];
            }
        }

        return $sortable;
    }

    private static function getForbiddenColumns(Model $model, ModelConfig $model_config): array
    {
        $global_forbidden = Config::get('api-query-builder.global_forbidden_columns', []);

        $forbidden = $model_config->getForbidden($global_forbidden);
        $forbidden = array_merge($forbidden, $model->getHidden());

        return array_unique($forbidden);
    }

    private static function resolveCastOperatorsKey(string $type, array $cast_operators): ?string
    {
        $raw = trim($type);

        if (array_key_exists($raw, $cast_operators)) {
            return $raw;
        }

        $normalized = strtolower($raw);
        $normalized = preg_replace('/\(.*\)$/', '', $normalized) ?? $normalized;

        if (array_key_exists($normalized, $cast_operators)) {
            return $normalized;
        }

        return null;
    }

    private static function getOperatorsForType(string $type): array
    {
        $normalized_type = strtolower(trim($type));
        $normalized_type = preg_replace('/\(.*\)$/', '', $normalized_type) ?? $normalized_type;

        $cast_operators = Config::get('api-query-builder.cast_operators', []);
        $cast_key = self::resolveCastOperatorsKey($type, $cast_operators);

        if ($cast_key !== null) {
            $result = array_map(
                fn ($class) => rtrim($class::operator(), ':'),
                $cast_operators[$cast_key]
            );
            sort($result);

            return $result;
        }

        $operators_config = new OperatorsConfig();

        $is_text = in_array($normalized_type, CategorizedValues::STRING_TYPES, true);
        $is_json = in_array($normalized_type, ['json', 'jsonb'], true);

        $result = [];

        foreach ($operators_config->registered as $callback_class) {
            $supported = match (true) {
                $is_json => $callback_class::supportsJsonTypes() || $callback_class::supportsTextTypes(),
                $is_text => $callback_class::supportsTextTypes(),
                default => $callback_class::supportsComparableTypes(),
            };

            if ($supported) {
                $result[] = rtrim($callback_class::operator(), ':');
            }
        }

        sort($result);

        return $result;
    }
}

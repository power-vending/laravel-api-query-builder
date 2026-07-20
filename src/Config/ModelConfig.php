<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Config;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use PowerVending\LaravelApiQueryBuilder\Support\RelationMethodNormalizer;

class ModelConfig
{
    public const CACHE_PREFIX = 'table_def_';

    public const CACHE_TTL = 86400;

    private Model $model;

    private array $config;

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->config = $this->hasConfig() ? $this->getConfig() : [];
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function getPrimaryColumn(): string
    {
        $table = $this->model->getTable();
        $primaryKey = $this->model->getKeyName();
        return $table . '.' . $primaryKey;
    }

    public function isPrimaryKey(string $column): bool
    {
        return $this->model->getKeyName() === $column;
    }

    public function hasConfig(): bool
    {
        return array_key_exists(get_class($this->model), (array) config('api-query-builder.model_options', []));
    }

    protected function getConfig(): array
    {
        return (array) config('api-query-builder.model_options.' . get_class($this->model), []);
    }

    public function getReturns(): array
    {
        if (array_key_exists('returns', $this->config) && $this->config['returns']) {
            return Arr::wrap($this->config['returns']);
        }

        return ['*'];
    }

    public function getRelations(): array
    {
        if (array_key_exists('relations', $this->config) && $this->config['relations']) {
            return Arr::wrap($this->config['relations']);
        }

        return [];
    }

    public function getOrderBy(): array
    {
        $parameters = [];

        if (array_key_exists('order_by', $this->config) && $this->config['order_by']) {
            foreach ($this->config['order_by'] as $key => $value) {
                $parameters[] = "$key=$value";
            }
        }

        return $parameters;
    }

    public function getForbidden(array $forbiddenKeys): array
    {
        $forbiddenKeys = $this->getEloquentExclusion($forbiddenKeys);
        $forbiddenKeys = $this->getForbiddenColumns($forbiddenKeys);

        return $forbiddenKeys;
    }

    public function getForbiddenRelations(array $forbiddenRelations): array
    {
        if (property_exists($this->model, 'apiQueryBuilderForbiddenRelations')) {
            $reflection = new \ReflectionClass($this->model);

            if ($reflection->hasProperty('apiQueryBuilderForbiddenRelations')) {
                $property = $reflection->getProperty('apiQueryBuilderForbiddenRelations');
                $modelForbiddenRelations = $property->getValue($this->model);

                if (is_array($modelForbiddenRelations) && !empty($modelForbiddenRelations)) {
                    $forbiddenRelations = array_merge($forbiddenRelations, $modelForbiddenRelations);
                }
            }
        }

        if (array_key_exists('forbidden_relations', $this->config) && $this->config['forbidden_relations']) {
            $forbiddenRelations = array_merge($forbiddenRelations, $this->config['forbidden_relations']);
        }

        $normalized = [];

        foreach ($forbiddenRelations as $relationPath) {
            if (!is_string($relationPath)) {
                continue;
            }

            $normalizedPath = $this->normalizeRelationPath($relationPath);

            if ($normalizedPath !== null) {
                $normalized[] = $normalizedPath;
            }
        }

        return array_values(array_unique($normalized));
    }

    public function isRelationForbidden(string $relationPath, array $globalForbiddenRelations = []): bool
    {
        $normalizedPath = $this->normalizeRelationPath($relationPath);

        if ($normalizedPath === null) {
            return false;
        }

        $forbiddenRelations = $this->getForbiddenRelations($globalForbiddenRelations);

        foreach ($forbiddenRelations as $forbiddenPath) {
            if ($normalizedPath === $forbiddenPath || str_starts_with($normalizedPath, $forbiddenPath . '.')) {
                return true;
            }
        }

        return false;
    }

    protected function getEloquentExclusion($forbiddenKeys): array
    {
        if (!array_key_exists('eloquent_exclusion', $this->config) || !$this->config['eloquent_exclusion']) {
            return $forbiddenKeys;
        }

        $guarded = $this->model->getGuarded();
        $fillable = $this->model->getFillable();

        if ($guarded[0] != '*') {
            $forbiddenKeys = array_merge($forbiddenKeys, $guarded);
        } elseif (count($fillable) > 0) {
            $forbiddenKeys = array_diff(array_keys($this->getModelColumns()), $fillable);
        }

        return $forbiddenKeys;
    }

    protected function getForbiddenColumns(array $forbiddenKeys): array
    {
        if (property_exists($this->model, 'apiQueryBuilderForbiddenColumns')) {
            $reflection = new \ReflectionClass($this->model);

            if ($reflection->hasProperty('apiQueryBuilderForbiddenColumns')) {
                $property = $reflection->getProperty('apiQueryBuilderForbiddenColumns');
                $modelForbidden = $property->getValue($this->model);

                if (is_array($modelForbidden) && !empty($modelForbidden)) {
                    $forbiddenKeys = array_merge($forbiddenKeys, $modelForbidden);
                }
            }
        }

        if (array_key_exists('forbidden_columns', $this->config) && $this->config['forbidden_columns']) {
            $forbiddenKeys = array_merge($forbiddenKeys, $this->config['forbidden_columns']);
        }

        return $forbiddenKeys;
    }

    public function getModelColumns(): array
    {
        $table = $this->model->getTable();
        $connection = $this->model->getConnection();

        if (Cache::has(self::CACHE_PREFIX . $table)) {
            return Cache::get(self::CACHE_PREFIX . $table);
        }

        $columns = $connection->getSchemaBuilder()->getColumnListing($table);
        $modelColumns = [];

        $this->registerEnumTypeForDoctrine($connection);

        try {
            foreach ($columns as $column) {
                $modelColumns[$column] = $connection->getSchemaBuilder()->getColumnType($table, $column);
            }
        } catch (Exception) {
            // leave model columns as an empty array and cache it.
        }

        Cache::put(self::CACHE_PREFIX . $table, $modelColumns, self::CACHE_TTL);

        return $modelColumns;
    }

    public function getTypeFromCast(string $column): ?string
    {
        $casts = $this->model->getCasts();

        if (!array_key_exists($column, $casts)) {
            return null;
        }

        $cast = (string) $casts[$column];

        if (stripos($cast, 'encrypted:') === 0) {
            $cast = explode(':', $cast, 2)[1] ?? '';
        } else {
            $cast = explode(':', $cast, 2)[0];
        }

        return $cast !== '' ? $cast : null;
    }

    protected function normalizeRelationPath(string $relationPath): ?string
    {
        $segments = array_values(array_filter(explode('.', trim($relationPath)), fn (string $segment) => $segment !== ''));

        if ($segments === []) {
            return null;
        }

        $normalizedSegments = [];

        foreach ($segments as $segment) {
            $normalizedSegment = RelationMethodNormalizer::normalize($segment);

            if ($normalizedSegment === null) {
                return null;
            }

            $normalizedSegments[] = $normalizedSegment;
        }

        return implode('.', $normalizedSegments);
    }

    protected function registerEnumTypeForDoctrine($connection): void
    {
        if (
            !class_exists('Doctrine\DBAL\Driver\AbstractSQLiteDriver') ||
            !method_exists($connection, 'getDoctrineSchemaManager') &&
            !method_exists($connection, 'createSchemaManager')
        ) {
            return;
        }

        $schema = method_exists($connection, 'createSchemaManager')
            ? $connection->createSchemaManager()
            : $connection->getDoctrineSchemaManager();

        $schema
            ->getDatabasePlatform()
            ->registerDoctrineTypeMapping('enum', 'string');
    }
}

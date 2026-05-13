<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PowerVending\LaravelApiQueryBuilder\Config\ModelConfig;
use PowerVending\LaravelApiQueryBuilder\Exceptions\InvalidRelationException;
use PowerVending\LaravelApiQueryBuilder\Http\Requests\SchemaRequest;
use PowerVending\LaravelApiQueryBuilder\Schema\QueryBuilderSchema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SchemaController
{
    public function show(SchemaRequest $request, string $resource): JsonResponse
    {
        $resource_models = Config::get('api-query-builder.resource_models', []);

        throw_if(
            !isset($resource_models[$resource]),
            NotFoundHttpException::class,
        );

        $model_class = $resource_models[$resource];
        $model = app($model_class);

        $default_relations = (new ModelConfig($model))->getRelations();
        $requested_relations = $request->input('relations', []);

        $relations = $this->mergeAndExpandRelations($model, $default_relations, $requested_relations);

        $schema = QueryBuilderSchema::forModel($model, $relations);

        return response()->json($schema);
    }

    private function mergeAndExpandRelations(Model $model, array $default_relations, array $requested_relations): array
    {
        $relations = $this->normalizeRelationPaths($default_relations);

        foreach ($this->normalizeRelationPaths($requested_relations) as $relation_path) {
            $relations[] = $relation_path;

            $related_model = $this->resolveRelationPathModel($model, $relation_path);

            if ($related_model === null) {
                throw new InvalidRelationException(
                    "Relation '$relation_path' does not exist on model '" . get_class($model) . "'."
                );
            }

            foreach ($this->getConfiguredRelationDescendants($related_model) as $descendant_path) {
                $relations[] = $relation_path . '.' . $descendant_path;
            }
        }

        return $this->normalizeRelationPaths($relations);
    }

    private function getConfiguredRelationDescendants(Model $model, array $visited = []): array
    {
        $model_class = get_class($model);

        if (in_array($model_class, $visited, true)) {
            return [];
        }

        $visited[] = $model_class;

        $descendants = [];
        $configured_relations = $this->normalizeRelationPaths((new ModelConfig($model))->getRelations());

        foreach ($configured_relations as $relation_path) {
            $descendants[] = $relation_path;

            $related_model = $this->resolveRelationPathModel($model, $relation_path);

            if ($related_model === null) {
                throw new InvalidRelationException(
                    "Relation '$relation_path' does not exist on model '$model_class'."
                );
            }

            foreach ($this->getConfiguredRelationDescendants($related_model, $visited) as $nested_descendant) {
                $descendants[] = $relation_path . '.' . $nested_descendant;
            }
        }

        return $this->normalizeRelationPaths($descendants);
    }

    private function resolveRelationPathModel(Model $model, string $path): ?Model
    {
        $segments = array_values(array_filter(explode('.', $path), fn (string $segment) => $segment !== ''));

        if ($segments === []) {
            return null;
        }

        $current_model = $model;

        foreach ($segments as $segment) {
            $method = Str::camel($segment);

            if (!method_exists($current_model, $method)) {
                return null;
            }

            try {
                $relation = $current_model->{$method}();
            } catch (\Throwable) {
                return null;
            }

            if (!$relation instanceof Relation) {
                return null;
            }

            $current_model = $relation->getRelated();
        }

        return $current_model;
    }

    private function normalizeRelationPaths(array $relations): array
    {
        $normalized = [];

        foreach ($relations as $relation) {
            if (!is_string($relation)) {
                continue;
            }

            $relation = trim($relation);

            if ($relation === '') {
                continue;
            }

            $normalized[] = $relation;
        }

        return array_values(array_unique($normalized));
    }
}

<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PowerVending\LaravelApiQueryBuilder\Exceptions\InvalidRelationException;
use PowerVending\LaravelApiQueryBuilder\Http\Requests\SchemaRequest;
use PowerVending\LaravelApiQueryBuilder\Schema\QueryBuilderSchema;
use PowerVending\LaravelApiQueryBuilder\Support\RelationMethodNormalizer;
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

        $default_relations = $this->discoverModelRelations($model);
        $requested_relations = $request->input('relations', []);

        $relations = $this->mergeAndExpandRelations($model, $default_relations, $requested_relations);

        $schema = QueryBuilderSchema::forModel($model, $relations);
        $schema['relations'] = $this->sortRelationNodesRecursively($schema['relations'] ?? []);

        return response()->json($schema);
    }

    private function sortRelationNodesRecursively(array $relations): array
    {
        if ($relations === []) {
            return $relations;
        }

        ksort($relations);

        foreach ($relations as $relation_name => $relation_schema) {
            if (!is_array($relation_schema)) {
                continue;
            }

            $relation_schema['relations'] = $this->sortRelationNodesRecursively(
                is_array($relation_schema['relations'] ?? null) ? $relation_schema['relations'] : []
            );

            $relations[$relation_name] = $relation_schema;
        }

        return $relations;
    }

    private function discoverModelRelations(Model $model): array
    {
        $relations = [];
        $reflection = new \ReflectionClass($model);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getDeclaringClass()->getName() !== get_class($model)) {
                continue;
            }

            try {
                $return_value = $model->{$method->getName()}();

                if ($return_value instanceof Relation) {
                    $relations[] = Str::snake($method->getName());
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return array_values(array_unique($relations));
    }

    private function mergeAndExpandRelations(Model $model, array $default_relations, array $requested_relations): array
    {
        $relations = $this->normalizeRelationPaths($default_relations);

        foreach ($this->normalizeRelationPaths($requested_relations) as $relation_path) {
            $relations[] = $relation_path;

            foreach ($this->buildPathPrefixes($relation_path) as $prefix) {
                $related_model = $this->resolveRelationPathModel($model, $prefix);

                if ($related_model === null) {
                    throw new InvalidRelationException(
                        "Relation '$prefix' does not exist on model '" . get_class($model) . "'."
                    );
                }

                foreach ($this->discoverModelRelations($related_model) as $child_relation) {
                    $relations[] = $prefix . '.' . $child_relation;
                }
            }
        }

        return $this->normalizeRelationPaths($relations);
    }

    private function buildPathPrefixes(string $path): array
    {
        $segments = array_values(array_filter(explode('.', $path), fn (string $segment) => $segment !== ''));
        $prefixes = [];

        for ($i = 0; $i < count($segments); $i++) {
            $prefixes[] = implode('.', array_slice($segments, 0, $i + 1));
        }

        return $prefixes;
    }

    private function resolveRelationPathModel(Model $model, string $path): ?Model
    {
        $segments = array_values(array_filter(explode('.', $path), fn (string $segment) => $segment !== ''));

        if ($segments === []) {
            return null;
        }

        $current_model = $model;

        foreach ($segments as $segment) {
            $method = RelationMethodNormalizer::normalize($segment);

            if ($method === null || !method_exists($current_model, $method)) {
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

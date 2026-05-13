<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
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

        $default_relations = $this->discoverModelRelations($model);
        $requested_relations = $request->input('relations', []);

        $relations = $this->mergeAndExpandRelations($model, $default_relations, $requested_relations);

        $schema = QueryBuilderSchema::forModel($model, $relations);

        return response()->json($schema);
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
        }

        return $this->normalizeRelationPaths($relations);
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

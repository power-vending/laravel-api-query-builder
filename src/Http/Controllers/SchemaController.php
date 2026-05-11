<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
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

        $relations = $request->has('relations') ? $request->input('relations', []) : null;

        $schema = QueryBuilderSchema::forModel($model, $relations);

        return response()->json($schema);
    }
}

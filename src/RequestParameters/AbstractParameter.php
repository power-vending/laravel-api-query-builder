<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\RequestParameters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use PowerVending\LaravelApiQueryBuilder\Config\ModelConfig;
use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;
use PowerVending\LaravelApiQueryBuilder\Exceptions\InvalidRelationException;
use PowerVending\LaravelApiQueryBuilder\Support\RelationMethodNormalizer;

abstract class AbstractParameter
{
    public Builder     $builder;

    public ModelConfig $modelConfig;

    protected array    $arguments;

    /**
     * AbstractParameter constructor.
     *
     * @param  array  $arguments
     * @param  Builder  $builder
     * @param  ModelConfig  $modelConfig
     */
    public function __construct(array $arguments, Builder $builder, ModelConfig $modelConfig)
    {
        $this->arguments = $arguments;
        $this->builder = $builder;
        $this->modelConfig = $modelConfig;
    }

    /**
     * JSON key by which the parameter will be recognized.
     *
     * @return string
     */
    abstract public static function getParameterName(): string;

    /**
     * Append the query to Eloquent builder.
     *
     * @throws ApiQueryBuilderException
     */
    abstract protected function appendQuery(): void;

    /**
     * @throws ApiQueryBuilderException
     */
    public function run(): void
    {
        $this->areArgumentsValid();
        $this->appendQuery();
    }

    /**
     * Check validity of fetched arguments and throw exception if it fails.
     *
     * @throws ApiQueryBuilderException
     */
    protected function areArgumentsValid(): void
    {
        if (count($this->arguments) < 1) {
            throw new ApiQueryBuilderException("Couldn't get values for '{$this->getParameterName()}'.");
        }

        // Override or extend on child objects if needed
    }

    /**
     * Validate relation path and return normalized (camel-cased) dot notation.
     *
     * @throws InvalidRelationException
     */
    protected function assertRelationExists(string $relationPath): string
    {
        $segments = array_values(array_filter(explode('.', $relationPath), fn (string $segment) => trim($segment) !== ''));

        if ($segments === []) {
            throw new InvalidRelationException("Relation '$relationPath' does not exist on model '" . get_class($this->builder->getModel()) . "'.");
        }

        $currentModel = $this->builder->getModel();
        $normalizedSegments = [];

        foreach ($segments as $segment) {
            $method = RelationMethodNormalizer::normalize($segment);
            $relation = $method === null ? 
                null : 
                $this->resolveRelation($currentModel, $method);

            if ($method === null || $relation === null) {
                throw new InvalidRelationException(
                    "Relation '$segment' does not exist on model '" . get_class($currentModel) . "'."
                );
            }

            $normalizedSegments[] = $method;
            $currentModel = $relation->getRelated();
        }

        return implode('.', $normalizedSegments);
    }

    protected function resolveRelation(Model $model, string $method): ?Relation
    {
        $globalForbiddenRelations = (array) config('api-query-builder.global_forbidden_relations', []);
        $modelConfig = new ModelConfig($model);

        if ($modelConfig->isRelationForbidden($method, $globalForbiddenRelations) || !method_exists($model, $method)) {
            return null;
        }

        try {
            $relation = $model->{$method}();
        } catch (\Throwable) {
            return null;
        }

        return $relation instanceof Relation ? $relation : null;
    }
}

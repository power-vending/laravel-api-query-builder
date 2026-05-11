<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\RequestParameters;

use Illuminate\Database\Eloquent\Builder;
use PowerVending\LaravelApiQueryBuilder\Config\ModelConfig;
use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;

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
}

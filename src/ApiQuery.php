<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use PowerVending\LaravelApiQueryBuilder\Config\{ModelConfig, RequestParametersConfig};
use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;
use PowerVending\LaravelApiQueryBuilder\RequestParameters\AbstractParameter;

class ApiQuery
{
    protected Builder     $builder;

    protected array       $input;

    protected ModelConfig $modelConfig;

    protected array       $registeredParameters;

    /**
     * ApiQuery constructor.
     *
     * @param  Builder  $builder
     * @param  array  $input
     *
     * @throws ApiQueryBuilderException
     */
    public function __construct(Builder $builder, array $input)
    {
        $this->builder = $builder;
        $this->input = $input;

        $this->forbidForExistingModels();

        $this->modelConfig = new ModelConfig($this->builder->getModel());
        $this->registeredParameters = (new RequestParametersConfig())->registered;
    }

    /**
     * @throws ApiQueryBuilderException
     */
    protected function forbidForExistingModels(): void
    {
        if ($this->builder->getModel()->exists) {
            throw new ApiQueryBuilderException('Searching is not allowed on already loaded models.');
        }
    }

    /**
     * Perform the search.
     *
     * @throws Exceptions\ApiQueryBuilderException
     */
    public function search(): void
    {
        $this->appendParameterQueries();
        $this->appendConfigQueries();
    }

    /**
     * Append all queries from registered parameters.
     *
     * @throws Exceptions\ApiQueryBuilderException
     */
    protected function appendParameterQueries(): void
    {
        foreach ($this->registeredParameters as $requestParameter) {
            if (!$this->parameterExists($requestParameter)) {
                // TODO: append config query?
                continue;
            }

            $this->instantiateRequestParameter($requestParameter)
                ->run();
        }
    }

    /**
     * Append all queries from config.
     */
    protected function appendConfigQueries(): void
    {
        // TODO: implement...or not
    }

    /**
     * @param  string  $requestParameter
     * @return bool
     */
    protected function parameterExists(string $requestParameter): bool
    {
        /**
         * @var AbstractParameter $requestParameter
         */
        return Arr::has($this->input, $requestParameter::getParameterName());
    }

    /**
     * @param  $requestParameter
     * @return AbstractParameter
     *
     * @throws ApiQueryBuilderException
     */
    protected function instantiateRequestParameter(string $requestParameter): AbstractParameter
    {
        if (!is_subclass_of($requestParameter, AbstractParameter::class)) {
            throw new ApiQueryBuilderException("$requestParameter must extend " . AbstractParameter::class);
        }

        $input = $this->wrapInput($requestParameter::getParameterName());

        return new $requestParameter($input, $this->builder, $this->modelConfig);
    }

    /**
     * Get input for given parameter name and wrap it as an array if it's not already an array.
     *
     * @param  string  $parameterName
     * @return array
     */
    protected function wrapInput(string $parameterName): array
    {
        return Arr::wrap(
            Arr::get($this->input, $parameterName)
        );
    }
}

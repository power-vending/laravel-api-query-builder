<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Config;

use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;

abstract class SearchConfig
{
    public array    $registered;

    protected array $config;

    /**
     * SearchConfig constructor.
     *
     * @throws ApiQueryBuilderException
     */
    public function __construct()
    {
        $this->config = config('api-query-builder');
        $this->register();
    }

    /**
     * Get registered classes from configuration file.
     *
     * @throws ApiQueryBuilderException
     */
    protected function register(): void
    {
        $key = $this->configKey();

        if (!array_key_exists($key, $this->config)) {
            throw new ApiQueryBuilderException("Config file is missing '$key'");
        }

        $this->registered = $this->config[$key];
    }

    abstract protected function configKey(): string;
}

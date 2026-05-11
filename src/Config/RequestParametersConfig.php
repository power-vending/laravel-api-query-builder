<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Config;

class RequestParametersConfig extends SearchConfig
{
    protected function configKey(): string
    {
        return 'request_parameters';
    }
}

<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\Config;

use Exception;
use PowerVending\LaravelApiQueryBuilder\Config\RequestParametersConfig;
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;

class RequestParametersConfigTest extends TestCase
{
    /** @test */
    public function passes_on_valid_config()
    {
        $requestParameters = new RequestParametersConfig();

        $this->assertNotEmpty($requestParameters->registered);
    }

    /** @test */
    public function throws_on_missing_config()
    {
        $this->expectException(Exception::class);

        config(['api-query-builder' => []]);

        new RequestParametersConfig();
    }
}

<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests;

use PowerVending\LaravelApiQueryBuilder\ApiQueryServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [ApiQueryServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        // perform environment setup
    }
}

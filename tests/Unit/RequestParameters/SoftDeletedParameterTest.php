<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\RequestParameters;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use PowerVending\LaravelApiQueryBuilder\Config\ModelConfig;
use PowerVending\LaravelApiQueryBuilder\RequestParameters\SoftDeletedParameter;
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;

class SoftDeletedParameterTest extends TestCase
{
    protected Builder $builder;

    protected ModelConfig $modelConfig;

    public function setUp(): void
    {
        parent::setUp();

        $this->builder = app(Builder::class);

        $this->modelConfig = Mockery::mock(ModelConfig::class);
    }

    /** @test */
    public function has_a_name()
    {
        $countParameter = new SoftDeletedParameter([], $this->builder, $this->modelConfig);

        $this->assertEquals('soft_deleted', $countParameter::getParameterName());
    }

    /** @test */
    public function accepts_valid_arguments()
    {
        foreach ([1, '1', true, 'true'] as $validArgument) {
            $countParameter = new SoftDeletedParameter([$validArgument], $this->builder, $this->modelConfig);
            $countParameter->run();
        }

        $this->assertTrue(true);
    }

    /** @test */
    public function rejects_non_bool_argument()
    {
        $this->expectException(Exception::class);

        $countParameter = new SoftDeletedParameter(['invalid'], $this->builder, $this->modelConfig);
        $countParameter->run();
    }

    /** @test */
    public function rejects_multiple_arguments()
    {
        $this->expectException(Exception::class);

        $countParameter = new SoftDeletedParameter([1, 1], $this->builder, $this->modelConfig);
        $countParameter->run();
    }
}

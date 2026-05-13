<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\RequestParameters;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use PowerVending\LaravelApiQueryBuilder\Config\ModelConfig;
use PowerVending\LaravelApiQueryBuilder\RequestParameters\ExceptsParameter;
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;

class ExceptsParameterTest extends TestCase
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
        $exceptsParameter = new ExceptsParameter([], $this->builder, $this->modelConfig);

        $this->assertEquals('excepts', $exceptsParameter::getParameterName());
    }

    /** @test */
    public function accepts_valid_arguments()
    {
        $this->modelConfig
            ->shouldReceive('getReturns')
            ->once()
            ->andReturn(['attribute1', 'attribute2', 'attribute3']);

        $exceptsParameter = new ExceptsParameter(
            ['attribute3'],
            $this->builder,
            $this->modelConfig
        );
        $exceptsParameter->run();

        $this->assertTrue(true);
    }

    /** @test */
    public function rejects_empty_argument()
    {
        $this->expectException(Exception::class);

        $exceptsParameter = new ExceptsParameter([], $this->builder, $this->modelConfig);
        $exceptsParameter->run();
    }

    /** @test */
    public function produces_query_using_returns_config()
    {
        $this->modelConfig
            ->shouldReceive('getReturns')
            ->once()
            ->andReturn(['attribute1', 'attribute2', 'attribute3']);

        $exceptsParameter = new ExceptsParameter(
            ['attribute2'],
            $this->builder,
            $this->modelConfig
        );
        $exceptsParameter->run();

        $query = 'select "attribute1", "attribute3"';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_query_using_model_columns_when_returns_is_wildcard()
    {
        $this->modelConfig
            ->shouldReceive('getReturns')
            ->once()
            ->andReturn(['*']);

        $this->modelConfig
            ->shouldReceive('getModelColumns')
            ->once()
            ->andReturn([
                'attribute1' => 'string',
                'attribute2' => 'string',
                'attribute3' => 'string',
            ]);

        $exceptsParameter = new ExceptsParameter(
            ['attribute2'],
            $this->builder,
            $this->modelConfig
        );
        $exceptsParameter->run();

        $query = 'select "attribute1", "attribute3"';

        $this->assertEquals($query, $this->builder->toSql());
    }
}

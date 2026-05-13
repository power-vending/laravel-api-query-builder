<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\RequestParameters;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use PowerVending\LaravelApiQueryBuilder\Config\ModelConfig;
use PowerVending\LaravelApiQueryBuilder\RequestParameters\OffsetParameter;
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;

class OffsetParameterTest extends TestCase
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
        $offsetParameter = new OffsetParameter([], $this->builder, $this->modelConfig);

        $this->assertEquals('offset', $offsetParameter::getParameterName());
    }

    /** @test */
    public function accepts_valid_arguments()
    {
        $offsetParameter = new OffsetParameter([15], $this->builder, $this->modelConfig);
        $offsetParameter->run();

        $this->assertTrue(true);
    }

    /** @test */
    public function rejects_non_numeric_argument()
    {
        $this->expectException(Exception::class);

        $offsetParameter = new OffsetParameter(['invalid'], $this->builder, $this->modelConfig);
        $offsetParameter->run();
    }

    /** @test */
    public function rejects_multiple_arguments()
    {
        $this->expectException(Exception::class);

        $offsetParameter = new OffsetParameter([1, 1], $this->builder, $this->modelConfig);
        $offsetParameter->run();
    }

    /** @test */
    public function produces_query()
    {
        $offsetParameter = new OffsetParameter([1], $this->builder, $this->modelConfig);
        $offsetParameter->run();

        $query = 'select * offset 1';

        $this->assertEquals($query, $this->builder->toSql());
    }
}

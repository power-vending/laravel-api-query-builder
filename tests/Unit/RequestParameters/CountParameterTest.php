<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\RequestParameters;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use PowerVending\LaravelApiQueryBuilder\Config\ModelConfig;
use PowerVending\LaravelApiQueryBuilder\RequestParameters\CountParameter;
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;

class CountParameterTest extends TestCase
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
        $countParameter = new CountParameter([], $this->builder, $this->modelConfig);

        $this->assertEquals('count', $countParameter::getParameterName());
    }

    /** @test */
    public function accepts_valid_arguments()
    {
        foreach ([1, '1', true, 'true'] as $validArgument) {
            $countParameter = new CountParameter([$validArgument], $this->builder, $this->modelConfig);
            $countParameter->run();
        }

        $this->assertTrue(true);
    }

    /** @test */
    public function rejects_non_bool_argument()
    {
        $this->expectException(Exception::class);

        $countParameter = new CountParameter(['invalid'], $this->builder, $this->modelConfig);
        $countParameter->run();
    }

    /** @test */
    public function rejects_multiple_arguments()
    {
        $this->expectException(Exception::class);

        $countParameter = new CountParameter([1, 1], $this->builder, $this->modelConfig);
        $countParameter->run();
    }

    /** @test */
    public function produces_query()
    {
        $countParameter = new CountParameter([true], $this->builder, $this->modelConfig);
        $countParameter->run();

        $query = 'select count(*) as count';

        $this->assertEquals($query, $this->builder->toSql());
    }
}

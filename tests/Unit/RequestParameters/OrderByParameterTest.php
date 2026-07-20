<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\RequestParameters;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Mockery;
use PowerVending\LaravelApiQueryBuilder\Config\ModelConfig;
use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;
use PowerVending\LaravelApiQueryBuilder\RequestParameters\OrderByParameter;
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;

class OrderByParameterTest extends TestCase
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
        $orderByParameter = new OrderByParameter([], $this->builder, $this->modelConfig);

        $this->assertEquals('order_by', $orderByParameter::getParameterName());
    }

    /** @test */
    public function accepts_valid_arguments()
    {
        $orderByParameter = new OrderByParameter(
            ['attribute1', 'attribute2' => 'desc'],
            $this->builder,
            $this->modelConfig
        );
        $orderByParameter->run();

        $this->assertTrue(true);
    }

    /** @test */
    public function rejects_empty_argument()
    {
        $this->expectException(Exception::class);

        $orderByParameter = new OrderByParameter([], $this->builder, $this->modelConfig);
        $orderByParameter->run();
    }

    /** @test */
    public function produces_query()
    {
        $orderByParameter = new OrderByParameter(
            ['attribute1', 'attribute2' => 'desc'],
            $this->builder,
            $this->modelConfig
        );
        $orderByParameter->run();

        $query = 'select * order by "attribute1" asc, "attribute2" desc';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_query_2()
    {
        $orderByParameter = new OrderByParameter(
            ['attribute1' => 'desc', 'attribute2' => 'asc'],
            $this->builder,
            $this->modelConfig
        );
        $orderByParameter->run();

        $query = 'select * order by "attribute1" desc, "attribute2" asc';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_query_with_relation()
    {
        // Create a TestModel instance with a BelongsTo relationship
        $model = new \PowerVending\LaravelApiQueryBuilder\Tests\TestModel();
        $builder = $model->newQuery();
        $modelConfig = new ModelConfig($model);

        $orderByParameter = new OrderByParameter(
            ['related.name' => 'desc'],
            $builder,
            $modelConfig
        );
        $orderByParameter->run();

        $sql = $builder->toSql();

        // Check if it contains a LEFT JOIN
        $this->assertStringContainsString('left join', $sql);
        $this->assertStringContainsString('"related"', $sql);
        $this->assertStringContainsString('order by', $sql);
    }

    /** @test */
    public function produces_query_with_deep_relation()
    {
        // related.nested.name => should produce chained LEFT JOINs and order by nested.name
        $model = new \PowerVending\LaravelApiQueryBuilder\Tests\TestModel();
        $builder = $model->newQuery();
        $modelConfig = new ModelConfig($model);

        $orderByParameter = new OrderByParameter(
            ['related.nested.name' => 'asc'],
            $builder,
            $modelConfig
        );
        $orderByParameter->run();

        $sql = $builder->toSql();

        // Should contain joins for both tables
        $this->assertStringContainsString('left join', $sql);
        $this->assertStringContainsString('"related"', $sql);
        $this->assertStringContainsString('"nested"', $sql);

        // Should order by the last relation table column
        $this->assertStringContainsString('order by "nested"."name" asc', $sql);
    }

    /** @test */
    public function qualifies_only_simple_select_columns_when_joining_for_relation_order()
    {
        $model = new \PowerVending\LaravelApiQueryBuilder\Tests\TestModel();
        $builder = $model->newQuery();
        $builder->addSelect('id', DB::raw('DATE(created_at) as created_date'));
        $modelConfig = new ModelConfig($model);

        (new OrderByParameter(
            ['related.name' => 'desc'],
            $builder,
            $modelConfig
        ))->run();

        $sql = $builder->toSql();

        $this->assertStringContainsString('"test"."id"', $sql);
        $this->assertStringContainsString('DATE(created_at) as created_date', $sql);
        $this->assertStringNotContainsString('"test".DATE(created_at)', $sql);
    }

    /** @test */
    public function throws_package_exception_when_relation_does_not_exist()
    {
        $this->expectException(ApiQueryBuilderException::class);

        $model = new \PowerVending\LaravelApiQueryBuilder\Tests\TestModel();
        $builder = $model->newQuery();
        $modelConfig = new ModelConfig($model);

        (new OrderByParameter(
            ['doesNotExist.name' => 'asc'],
            $builder,
            $modelConfig
        ))->run();
    }
}

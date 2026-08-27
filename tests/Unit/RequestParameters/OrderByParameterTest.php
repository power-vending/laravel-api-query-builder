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
use PowerVending\LaravelApiQueryBuilder\Tests\Fixtures\{CategoryModel, TicketModel};
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

    /** @test */
    public function qualifies_simple_columns_with_the_model_table()
    {
        $model = new \PowerVending\LaravelApiQueryBuilder\Tests\TestModel();
        $builder = $model->newQuery();

        (new OrderByParameter(['name' => 'asc', 'created_at'], $builder, new ModelConfig($model)))->run();

        $this->assertStringContainsString('order by "test"."name" asc, "test"."created_at" asc', $builder->toSql());
    }

    /** @test */
    public function accepts_columns_already_prefixed_with_the_model_table()
    {
        $model = new \PowerVending\LaravelApiQueryBuilder\Tests\TestModel();
        $builder = $model->newQuery();

        (new OrderByParameter(['test.name' => 'asc'], $builder, new ModelConfig($model)))->run();

        $sql = $builder->toSql();

        $this->assertStringContainsString('order by "test"."name" asc', $sql);
        $this->assertStringNotContainsString('left join', $sql);
    }

    /** @test */
    public function accepts_columns_prefixed_with_a_table_already_joined_by_the_query()
    {
        $model = new \PowerVending\LaravelApiQueryBuilder\Tests\TestModel();
        $builder = $model->newQuery()->leftJoin('related', 'related.id', '=', 'test.related_id');

        (new OrderByParameter(['related.name' => 'asc'], $builder, new ModelConfig($model)))->run();

        $sql = $builder->toSql();

        $this->assertStringContainsString('order by "related"."name" asc', $sql);
        $this->assertSame(1, substr_count($sql, 'left join'));
    }

    /** @test */
    public function qualifies_columns_with_the_table_alias_when_the_query_uses_one()
    {
        $model = new \PowerVending\LaravelApiQueryBuilder\Tests\TestModel();
        $builder = $model->newQuery()->from('test as t');

        (new OrderByParameter(['name' => 'asc'], $builder, new ModelConfig($model)))->run();

        $this->assertStringContainsString('order by "t"."name" asc', $builder->toSql());
    }

    /** @test */
    public function aliases_the_joined_table_when_ordering_by_a_self_referencing_relation()
    {
        $model = new CategoryModel();
        $builder = $model->newQuery();

        (new OrderByParameter(['parent.name' => 'asc'], $builder, new ModelConfig($model)))->run();

        $sql = $builder->toSql();

        $this->assertStringContainsString('left join "categories" as "categories_2" on "categories"."parent_id" = "categories_2"."id"', $sql);
        $this->assertStringContainsString('order by "categories_2"."name" asc', $sql);
    }

    /** @test */
    public function aliases_the_second_join_when_two_relations_point_to_the_same_table()
    {
        $model = new TicketModel();
        $builder = $model->newQuery();

        (new OrderByParameter(
            ['created_by.name' => 'asc', 'updated_by.name' => 'desc'],
            $builder,
            new ModelConfig($model)
        ))->run();

        $sql = $builder->toSql();

        $this->assertStringContainsString('left join "authors" on "tickets"."created_by" = "authors"."id"', $sql);
        $this->assertStringContainsString('left join "authors" as "authors_2" on "tickets"."updated_by" = "authors_2"."id"', $sql);
        $this->assertStringContainsString('order by "authors"."name" asc, "authors_2"."name" desc', $sql);
    }

    /** @test */
    public function orders_by_a_morph_many_relation_constraining_the_morph_type()
    {
        $model = new TicketModel();
        $builder = $model->newQuery();

        (new OrderByParameter(['comments.body' => 'asc'], $builder, new ModelConfig($model)))->run();

        $sql = $builder->toSql();

        $this->assertStringContainsString('left join "comments" on "comments"."commentable_id" = "tickets"."id" and "comments"."commentable_type" = ?', $sql);
        $this->assertStringContainsString('group by "tickets"."id"', $sql);
        $this->assertStringContainsString('order by "comments"."body" asc', $sql);
    }

    /** @test */
    public function orders_by_a_belongs_to_many_relation_through_its_pivot()
    {
        $model = new TicketModel();
        $builder = $model->newQuery();

        (new OrderByParameter(['partners.name' => 'asc'], $builder, new ModelConfig($model)))->run();

        $sql = $builder->toSql();

        $this->assertStringContainsString('left join "partner_ticket" on "partner_ticket"."ticket_id" = "tickets"."id"', $sql);
        $this->assertStringContainsString('left join "partners" on "partners"."id" = "partner_ticket"."partner_id"', $sql);
        $this->assertStringContainsString('order by "partners"."name" asc', $sql);
    }
}

<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\RequestParameters;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use PowerVending\LaravelApiQueryBuilder\Config\ModelConfig;
use PowerVending\LaravelApiQueryBuilder\RequestParameters\SearchParameter;
use PowerVending\LaravelApiQueryBuilder\Tests\{TestCase, TestModel};

class SearchParameterTest extends TestCase
{
    protected Builder $builder;

    protected ModelConfig $modelConfig;

    public function setUp(): void
    {
        parent::setUp();

        $this->builder = app(Builder::class);
        $this->builder->setModel(new TestModel());

        $this->modelConfig = Mockery::mock(ModelConfig::class);
        $this->modelConfig->shouldReceive('getForbidden')->andReturn([]);
        $this->modelConfig->shouldReceive('getModelColumns')->andReturn([]);
        $this->modelConfig->shouldReceive('getTypeFromCast')->andReturn(null);
        $this->modelConfig->shouldReceive('isPrimaryKey')->andReturn(false);
    }

    protected function createSearchParameter(array $arguments): SearchParameter
    {
        return new SearchParameter($arguments, $this->builder, $this->modelConfig);
    }

    protected function createStringTypeSearchParameter(array $arguments): SearchParameter
    {
        $modelConfig = Mockery::mock(ModelConfig::class);
        $modelConfig->shouldReceive('getForbidden')->andReturn([]);
        $modelConfig->shouldReceive('getModelColumns')->andReturn(
            array_fill_keys(array_keys($arguments), 'string')
        );
        $modelConfig->shouldReceive('getTypeFromCast')->andReturn(null);
        $modelConfig->shouldReceive('isPrimaryKey')->andReturn(false);

        return new SearchParameter($arguments, $this->builder, $modelConfig);
    }

    /** @test */
    public function has_a_name()
    {
        $searchParameter = $this->createSearchParameter([]);

        $this->assertEquals('search', $searchParameter::getParameterName());
    }

    /** @test */
    public function accepts_valid_arguments()
    {
        $arguments = [
            'attribute1' => 'EQ:123',
            'attribute2' => 'EQ:456',
        ];

        $searchParameter = $this->createSearchParameter($arguments);
        $searchParameter->run();

        $this->assertTrue(true);
    }

    /** @test */
    public function rejects_empty_argument()
    {
        $this->expectException(Exception::class);

        $searchParameter = $this->createSearchParameter([]);
        $searchParameter->run();
    }

    /** @test */
    public function produces_where_in_query()
    {
        $arguments = [
            'attribute1' => 'EQ:123',
            'attribute2' => 'EQ:456',
        ];

        $searchParameter = $this->createSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" in (?))) and (("attribute2" in (?))))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_where_in_multiple_query()
    {
        $arguments = [
            'attribute1' => 'EQ:123;456',
        ];

        $searchParameter = $this->createSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" in (?, ?))))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_where_not_in_query()
    {
        $arguments = [
            'attribute1' => 'NE:123',
        ];

        $searchParameter = $this->createSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" not in (?))))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_where_not_in_multiple_query()
    {
        $arguments = [
            'attribute1' => 'NE:123;456',
        ];

        $searchParameter = $this->createSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" not in (?, ?))))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_less_than_query()
    {
        $arguments = [
            'attribute1' => 'LT:123',
        ];

        $searchParameter = $this->createSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" < ?)))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_less_than_or_equals_query()
    {
        $arguments = [
            'attribute1' => 'LE:123',
        ];

        $searchParameter = $this->createSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" <= ?)))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_greater_than_query()
    {
        $arguments = [
            'attribute1' => 'GT:123',
        ];

        $searchParameter = $this->createSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" > ?)))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_greater_than_or_equals_query()
    {
        $arguments = [
            'attribute1' => 'GE:123',
        ];

        $searchParameter = $this->createSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" >= ?)))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_between_query()
    {
        $arguments = [
            'attribute1' => 'BT:123;456',
        ];

        $searchParameter = $this->createSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" between ? and ?)))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_not_between_query()
    {
        $arguments = [
            'attribute1' => 'NB:123;456',
        ];

        $searchParameter = $this->createSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" not between ? and ?)))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_like_query_for_single_string_value()
    {
        $arguments = [
            'attribute1' => 'EQ:foo',
        ];

        $searchParameter = $this->createStringTypeSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" LIKE ?)))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_in_query_for_string_column_with_multiple_values()
    {
        $arguments = [
            'attribute1' => 'EQ:foo;bar',
        ];

        $searchParameter = $this->createStringTypeSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" in (?, ?))))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_not_like_query_for_negated_micro_operator_in_eq()
    {
        $arguments = [
            'attribute1' => 'EQ:!foo',
        ];

        $searchParameter = $this->createStringTypeSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" NOT LIKE ?)))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_not_like_query_for_ne_operator_with_single_string_value()
    {
        $arguments = [
            'attribute1' => 'NE:foo',
        ];

        $searchParameter = $this->createStringTypeSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" NOT LIKE ?)))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_not_in_query_for_ne_operator_with_multiple_string_values()
    {
        $arguments = [
            'attribute1' => 'NE:foo;bar',
        ];

        $searchParameter = $this->createStringTypeSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" not in (?, ?))))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_like_query_with_trailing_wildcard()
    {
        $arguments = [
            'attribute1' => 'EQ:foo%',
        ];

        $searchParameter = $this->createSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" LIKE ?)))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_like_query_with_leading_wildcard()
    {
        $arguments = [
            'attribute1' => 'EQ:%foo',
        ];

        $searchParameter = $this->createSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" LIKE ?)))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_like_query_with_surrounding_wildcards()
    {
        $arguments = [
            'attribute1' => 'EQ:%foo%',
        ];

        $searchParameter = $this->createSearchParameter($arguments);
        $searchParameter->run();

        $query = 'select * from "test" where ((("attribute1" LIKE ?)))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_like_query_for_single_varchar_value()
    {
        $modelConfig = Mockery::mock(ModelConfig::class);
        $modelConfig->shouldReceive('getForbidden')->andReturn([]);
        $modelConfig->shouldReceive('getModelColumns')->andReturn(['description' => 'varchar']);
        $modelConfig->shouldReceive('getTypeFromCast')->andReturn(null);
        $modelConfig->shouldReceive('isPrimaryKey')->andReturn(false);

        $arguments = ['description' => 'EQ:João'];
        $searchParameter = new SearchParameter($arguments, $this->builder, $modelConfig);
        $searchParameter->run();

        $query = 'select * from "test" where ((("description" LIKE ?)))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_like_query_for_single_text_value()
    {
        $modelConfig = Mockery::mock(ModelConfig::class);
        $modelConfig->shouldReceive('getForbidden')->andReturn([]);
        $modelConfig->shouldReceive('getModelColumns')->andReturn(['content' => 'text']);
        $modelConfig->shouldReceive('getTypeFromCast')->andReturn(null);
        $modelConfig->shouldReceive('isPrimaryKey')->andReturn(false);

        $arguments = ['content' => 'EQ:example'];
        $searchParameter = new SearchParameter($arguments, $this->builder, $modelConfig);
        $searchParameter->run();

        $query = 'select * from "test" where ((("content" LIKE ?)))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_like_query_for_single_char_value()
    {
        $modelConfig = Mockery::mock(ModelConfig::class);
        $modelConfig->shouldReceive('getForbidden')->andReturn([]);
        $modelConfig->shouldReceive('getModelColumns')->andReturn(['code' => 'char']);
        $modelConfig->shouldReceive('getTypeFromCast')->andReturn(null);
        $modelConfig->shouldReceive('isPrimaryKey')->andReturn(false);

        $arguments = ['code' => 'EQ:ABC'];
        $searchParameter = new SearchParameter($arguments, $this->builder, $modelConfig);
        $searchParameter->run();

        $query = 'select * from "test" where ((("code" LIKE ?)))';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function produces_in_query_for_varchar_column_with_multiple_values()
    {
        $modelConfig = Mockery::mock(ModelConfig::class);
        $modelConfig->shouldReceive('getForbidden')->andReturn([]);
        $modelConfig->shouldReceive('getModelColumns')->andReturn(['description' => 'varchar']);
        $modelConfig->shouldReceive('getTypeFromCast')->andReturn(null);
        $modelConfig->shouldReceive('isPrimaryKey')->andReturn(false);

        $arguments = ['description' => 'EQ:João;Maria'];
        $searchParameter = new SearchParameter($arguments, $this->builder, $modelConfig);
        $searchParameter->run();

        $query = 'select * from "test" where ((("description" in (?, ?))))';

        $this->assertEquals($query, $this->builder->toSql());
    }
}

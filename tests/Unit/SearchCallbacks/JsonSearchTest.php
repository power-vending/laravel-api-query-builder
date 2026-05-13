<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\SearchCallbacks;

use Illuminate\Database\Eloquent\Builder;
use Mockery;
use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;
use PowerVending\LaravelApiQueryBuilder\SearchCallbacks\JsonSearch;
use PowerVending\LaravelApiQueryBuilder\SearchParser;
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;

class JsonSearchTest extends TestCase
{
    protected Builder $builder;

    protected SearchParser $searchParser;

    public function setUp(): void
    {
        parent::setUp();

        $this->builder = app(Builder::class);

        $this->searchParser = Mockery::mock(SearchParser::class);
        $this->searchParser->type = 'text';
        $this->searchParser->column = 'value';
        $this->searchParser->shouldReceive('isModelRelation')->andReturn(false);
    }

    /** @test */
    public function produces_json_search_query_with_wildcard_binding()
    {
        $this->searchParser->values = ['%bluetooth%'];

        new JsonSearch($this->builder, $this->searchParser);

        $this->assertStringContainsString('JSON_SEARCH("value", \'one\', ?) IS NOT NULL', $this->builder->toSql());
        $this->assertEquals(['%bluetooth%'], $this->builder->getBindings());
    }

    /** @test */
    public function produces_or_where_raw_for_multiple_values()
    {
        $this->searchParser->values = ['%bluetooth%', '%wifi%'];

        new JsonSearch($this->builder, $this->searchParser);

        $sql = $this->builder->toSql();
        $this->assertStringContainsString('JSON_SEARCH("value", \'one\', ?) IS NOT NULL', $sql);
        $this->assertStringContainsString('or', strtolower($sql));
        $this->assertEquals(['%bluetooth%', '%wifi%'], $this->builder->getBindings());
    }

    /** @test */
    public function throws_when_no_valid_arguments_provided()
    {
        $this->expectException(ApiQueryBuilderException::class);
        $this->expectExceptionMessage("No valid arguments for 'json_search' operator.");

        $this->searchParser->values = [];

        new JsonSearch($this->builder, $this->searchParser);
    }

    /** @test */
    public function throws_on_negated_value_micro_operator()
    {
        $this->expectException(ApiQueryBuilderException::class);
        $this->expectExceptionMessage("Wrong parameter type(s) for 'json_search' operator.");

        $this->searchParser->values = ['!%wifi%'];

        new JsonSearch($this->builder, $this->searchParser);
    }

    /** @test */
    public function throws_on_null_micro_operator()
    {
        $this->expectException(ApiQueryBuilderException::class);
        $this->expectExceptionMessage("Wrong parameter type(s) for 'json_search' operator.");

        $this->searchParser->values = ['null'];

        new JsonSearch($this->builder, $this->searchParser);
    }

    /** @test */
    public function throws_on_not_null_micro_operator()
    {
        $this->expectException(ApiQueryBuilderException::class);
        $this->expectExceptionMessage("Wrong parameter type(s) for 'json_search' operator.");

        $this->searchParser->values = ['!null'];

        new JsonSearch($this->builder, $this->searchParser);
    }
}

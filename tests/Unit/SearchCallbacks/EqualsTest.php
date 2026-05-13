<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\SearchCallbacks;

use Illuminate\Database\Eloquent\Builder;
use Mockery;
use PowerVending\LaravelApiQueryBuilder\SearchCallbacks\Equals;
use PowerVending\LaravelApiQueryBuilder\SearchParser;
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;

class EqualsTest extends TestCase
{
    protected Builder $builder;

    protected SearchParser $searchParser;

    public function setUp(): void
    {
        parent::setUp();

        /**
         * @var Builder $builder
         */
        $this->builder = app(Builder::class);

        $this->searchParser = Mockery::mock(SearchParser::class);
        $this->searchParser->type = 'test';
        $this->searchParser->column = 'test';
        $this->searchParser->shouldReceive('isModelRelation')->andReturn(false);
    }

    /** @test */
    public function produces_query()
    {
        $this->searchParser->values = ['123'];

        new Equals($this->builder, $this->searchParser);

        $sql = 'select * where "test" in (?)';

        $this->assertEquals($sql, $this->builder->toSql());
    }
}

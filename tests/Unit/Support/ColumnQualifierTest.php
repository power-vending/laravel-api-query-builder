<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use PowerVending\LaravelApiQueryBuilder\Support\ColumnQualifier;
use PowerVending\LaravelApiQueryBuilder\Tests\{TestCase, TestModel};

class ColumnQualifierTest extends TestCase
{
    /** @test */
    public function qualifies_simple_column_with_model_table()
    {
        $builder = (new TestModel())->newQuery();

        $this->assertSame('test.created_at', ColumnQualifier::qualify($builder, 'created_at'));
    }

    /** @test */
    public function leaves_already_qualified_columns_unchanged()
    {
        $builder = (new TestModel())->newQuery();

        $this->assertSame('companies.name', ColumnQualifier::qualify($builder, 'companies.name'));
    }

    /** @test */
    public function returns_column_unchanged_when_builder_has_no_model()
    {
        $builder = app(Builder::class);

        $this->assertSame('created_at', ColumnQualifier::qualify($builder, 'created_at'));
    }

    /** @test */
    public function qualifies_simple_select_columns_with_parent_table()
    {
        $this->assertSame('test.id', ColumnQualifier::qualifyForSelect('id', 'test'));
        $this->assertSame('test."name"', ColumnQualifier::qualifyForSelect('"name"', 'test'));
    }

    /** @test */
    public function leaves_select_expressions_and_qualified_columns_unchanged()
    {
        $expression = DB::raw('count("status") as count_status');

        $this->assertSame('companies.name', ColumnQualifier::qualifyForSelect('companies.name', 'test'));
        $this->assertSame('test.*', ColumnQualifier::qualifyForSelect('test.*', 'test'));
        $this->assertSame($expression, ColumnQualifier::qualifyForSelect($expression, 'test'));
        $this->assertSame(
            'DATE(created_at) as created_date',
            ColumnQualifier::qualifyForSelect('DATE(created_at) as created_date', 'test')
        );
    }

    /** @test */
    public function qualifies_with_the_query_alias_instead_of_the_model_table()
    {
        $builder = (new TestModel())->newQuery()->from('test as t');

        $this->assertSame('t.created_at', ColumnQualifier::qualify($builder, 'created_at'));
        $this->assertSame('t', ColumnQualifier::tableAlias($builder));
    }

    /** @test */
    public function lists_the_from_and_every_joined_table()
    {
        $builder = (new TestModel())->newQuery()
            ->leftJoin('related', 'related.id', '=', 'test.related_id')
            ->leftJoin('nested as n', 'n.id', '=', 'related.nested_id');

        $this->assertSame(['test', 'related', 'n'], ColumnQualifier::knownTableAliases($builder));
    }

    /** @test */
    public function detects_columns_prefixed_with_a_table_reachable_by_the_query()
    {
        $builder = (new TestModel())->newQuery()->leftJoin('related', 'related.id', '=', 'test.related_id');

        $this->assertTrue(ColumnQualifier::isQualifiedWithKnownTable($builder, 'test.name'));
        $this->assertTrue(ColumnQualifier::isQualifiedWithKnownTable($builder, 'related.name'));
        $this->assertFalse(ColumnQualifier::isQualifiedWithKnownTable($builder, 'company.name'));
        $this->assertFalse(ColumnQualifier::isQualifiedWithKnownTable($builder, 'name'));
    }

    /** @test */
    public function returns_table_alias_as_null_when_builder_has_no_model_and_no_from()
    {
        $this->assertNull(ColumnQualifier::tableAlias(app(Builder::class)));
    }
}

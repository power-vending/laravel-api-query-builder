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
}

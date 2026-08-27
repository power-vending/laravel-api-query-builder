<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit;

use PowerVending\LaravelApiQueryBuilder\ApiQuery;
use PowerVending\LaravelApiQueryBuilder\Tests\Fixtures\{CategoryModel, TicketModel};
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;

/**
 * Filtering and then ordering must never leave a bare column behind, otherwise the
 * database rejects the query with an "ambiguous column" error.
 */
class QueryQualificationTest extends TestCase
{
    protected function sqlFor(array $input, $model): string
    {
        $builder = $model->newQuery();

        (new ApiQuery($builder, $input))->search();

        return $builder->toSql();
    }

    /** @test */
    public function qualifies_relation_columns_searched_with_dot_notation()
    {
        // The pivot join inside the 'exists' sub-query makes a bare 'id' ambiguous.
        $sql = $this->sqlFor(['search' => ['partners.id' => 'EQ:1']], new TicketModel());

        $this->assertStringContainsString('"partners"."id" in (?)', $sql);
    }

    /** @test */
    public function qualifies_relation_columns_with_the_sub_query_alias_on_self_relations()
    {
        // Eloquent aliases the table when a relation points back to the parent table.
        $sql = $this->sqlFor(['search' => ['children.name' => 'EQ:foo']], new CategoryModel());

        $this->assertStringContainsString('from "categories" as "laravel_reserved_0"', $sql);
        $this->assertStringContainsString('"laravel_reserved_0"."name" in (?)', $sql);
        $this->assertStringNotContainsString('and "name" in (?)', $sql);
    }

    /** @test */
    public function qualifies_both_sides_when_filtering_and_then_ordering_by_the_same_relation()
    {
        $sql = $this->sqlFor([
            'search'   => ['partners.name' => 'EQ:foo'],
            'order_by' => ['partners.name' => 'asc'],
        ], new TicketModel());

        $this->assertStringContainsString('"partners"."name" in (?)', $sql);
        $this->assertStringContainsString('order by "partners"."name" asc', $sql);
        $this->assertStringContainsString('select "tickets".* from "tickets"', $sql);
        $this->assertStringContainsString('group by "tickets"."id"', $sql);
    }

    /** @test */
    public function qualifies_plain_columns_when_the_order_by_joined_a_table_with_the_same_column()
    {
        $sql = $this->sqlFor([
            'search'   => ['name' => 'EQ:foo'],
            'order_by' => ['partners.name' => 'asc', 'name' => 'desc'],
        ], new TicketModel());

        $this->assertStringContainsString('"tickets"."name" in (?)', $sql);
        $this->assertStringContainsString('order by "partners"."name" asc, "tickets"."name" desc', $sql);
    }
}

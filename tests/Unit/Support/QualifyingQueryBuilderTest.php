<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\Support;

use PowerVending\LaravelApiQueryBuilder\Tests\DatabaseTestCase;
use PowerVending\LaravelApiQueryBuilder\Tests\Fixtures\{CompanyFixtureModel, TerminalModel};

class QualifyingQueryBuilderTest extends DatabaseTestCase
{
    /** @test */
    public function qualifies_a_bare_where_added_after_the_order_by_join()
    {
        $query = TerminalModel::requestQuery(['order_by' => ['tenant.id' => 'asc']])
            ->where('id', 1);

        $this->assertStringContainsString('"terminals"."id" = ?', $query->toSql());
    }

    /** @test */
    public function qualifies_a_bare_where_added_before_the_order_by_join()
    {
        $query = TerminalModel::where('id', 1)
            ->requestQuery(['order_by' => ['tenant.id' => 'asc']]);

        $this->assertStringContainsString('"terminals"."id" = ?', $query->toSql());
    }

    /** @test */
    public function qualifies_bare_columns_inside_a_nested_closure_written_before_the_join()
    {
        $query = TerminalModel::where(function ($builder) {
            $builder->where('id', 1)->orWhere('id', 2);
        })->requestQuery(['order_by' => ['tenant.id' => 'asc']]);

        $sql = $query->toSql();

        $this->assertStringContainsString('("terminals"."id" = ? or "terminals"."id" = ?)', $sql);
    }

    /** @test */
    public function qualifies_bare_columns_inside_a_nested_closure_written_after_the_join()
    {
        $query = TerminalModel::requestQuery(['order_by' => ['tenant.id' => 'asc']])
            ->where(function ($builder) {
                $builder->where('id', 1)->orWhere('id', 2);
            });

        $this->assertStringContainsString('("terminals"."id" = ? or "terminals"."id" = ?)', $query->toSql());
    }

    /** @test */
    public function keeps_bindings_aligned_when_qualifying_a_nested_closure()
    {
        $query = TerminalModel::requestQuery(['order_by' => ['tenant.id' => 'asc']])
            ->where(function ($builder) {
                $builder->where('id', 7)->orWhere('name', 'T1');
            });

        $this->assertSame([7, 'T1'], $query->getBindings());
    }

    /** @test */
    public function qualifies_where_in_where_not_in_where_null_and_where_not_null()
    {
        $query = TerminalModel::requestQuery(['order_by' => ['tenant.id' => 'asc']])
            ->whereIn('id', [1, 2])
            ->whereNotIn('id', [3])
            ->whereNull('name')
            ->whereNotNull(['id', 'name']);

        $sql = $query->toSql();

        $this->assertStringContainsString('"terminals"."id" in (?, ?)', $sql);
        $this->assertStringContainsString('"terminals"."id" not in (?)', $sql);
        $this->assertStringContainsString('"terminals"."name" is null', $sql);
        $this->assertStringContainsString('"terminals"."id" is not null and "terminals"."name" is not null', $sql);
    }

    /** @test */
    public function leaves_columns_that_only_exist_on_a_joined_table_untouched()
    {
        // 'configuration_id' exists on the pivot only, so it is never ambiguous.
        $company = CompanyFixtureModel::create(['name' => 'ACME']);

        $sql = $company->configurations()->whereIn('configuration_id', [1, 2])->toSql();

        $this->assertStringContainsString('"configuration_id" in (?, ?)', $sql);
        $this->assertStringNotContainsString('"configurations"."configuration_id"', $sql);
    }

    /** @test */
    public function leaves_columns_that_only_exist_on_the_base_table_untouched()
    {
        // 'serial' exists on 'terminals' only, so the join cannot make it ambiguous.
        $query = TerminalModel::requestQuery(['order_by' => ['tenant.id' => 'asc']])
            ->where('serial', 'ABC');

        $sql = $query->toSql();

        $this->assertStringContainsString('"serial" = ?', $sql);
        $this->assertStringNotContainsString('"terminals"."serial"', $sql);
    }

    /** @test */
    public function leaves_already_qualified_columns_untouched()
    {
        $query = TerminalModel::requestQuery(['order_by' => ['tenant.id' => 'asc']])
            ->where('tenants.id', 1);

        $sql = $query->toSql();

        $this->assertStringContainsString('"tenants"."id" = ?', $sql);
        $this->assertStringNotContainsString('"terminals"."tenants"', $sql);
    }

    /** @test */
    public function leaves_columns_untouched_when_the_query_has_no_joins()
    {
        $sql = TerminalModel::where('id', 1)->toSql();

        $this->assertStringContainsString('"id" = ?', $sql);
        $this->assertStringNotContainsString('"terminals"."id"', $sql);
    }

    /** @test */
    public function the_qualified_query_actually_runs_and_filters_on_the_base_table()
    {
        $tenantA = \PowerVending\LaravelApiQueryBuilder\Tests\Fixtures\TenantModel::create(['name' => 'Tenant A']);
        $tenantB = \PowerVending\LaravelApiQueryBuilder\Tests\Fixtures\TenantModel::create(['name' => 'Tenant B']);

        $first = TerminalModel::create(['name' => 'T1', 'tenant_id' => $tenantB->id]);
        $second = TerminalModel::create(['name' => 'T2', 'tenant_id' => $tenantA->id]);

        $results = TerminalModel::whereNotIn('id', [$first->id])
            ->requestQuery(['order_by' => ['tenant.id' => 'asc']])
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame($second->id, $results->first()->id);
    }
}

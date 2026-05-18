<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\Schema;

use Illuminate\Support\Facades\Schema;
use PowerVending\LaravelApiQueryBuilder\Exceptions\InvalidRelationException;
use PowerVending\LaravelApiQueryBuilder\Schema\QueryBuilderSchema;
use PowerVending\LaravelApiQueryBuilder\SearchCallbacks\{Equals, GreaterThan, NotEquals};
use PowerVending\LaravelApiQueryBuilder\Tests\{TestCase, TestModel};

class QueryBuilderSchemaTest extends TestCase
{
    /** @test */
    public function normalizes_varchar_type_and_uses_text_operators()
    {
        Schema::shouldReceive('hasTable')->andReturn(true);
        Schema::shouldReceive('getColumns')->with('test')->andReturn([
            ['name' => 'serial_number', 'type' => 'varchar(191)', 'nullable' => true],
        ]);

        $schema = QueryBuilderSchema::forModel(new TestModel(), []);
        $operators = $schema['searchable_columns']['serial_number']['operators'];

        $this->assertContains('LIKE', $operators);
        $this->assertContains('STARTS_WITH', $operators);
        $this->assertContains('ENDS_WITH', $operators);
        $this->assertContains('EQ', $operators);
        $this->assertContains('NE', $operators);

        $this->assertNotContains('GT', $operators);
        $this->assertNotContains('LT', $operators);
        $this->assertNotContains('BT', $operators);
        $this->assertNotContains('NB', $operators);
    }

    /** @test */
    public function json_type_includes_json_and_text_operators_but_not_comparable_ones()
    {
        Schema::shouldReceive('hasTable')->andReturn(true);
        Schema::shouldReceive('getColumns')->with('test')->andReturn([
            ['name' => 'payload', 'type' => 'json', 'nullable' => true],
        ]);

        $schema = QueryBuilderSchema::forModel(new TestModel(), []);
        $operators = $schema['searchable_columns']['payload']['operators'];

        $this->assertContains('JSON_SEARCH', $operators);
        $this->assertContains('LIKE', $operators);
        $this->assertContains('STARTS_WITH', $operators);
        $this->assertContains('ENDS_WITH', $operators);
        $this->assertContains('EQ', $operators);
        $this->assertContains('NE', $operators);

        $this->assertNotContains('GT', $operators);
        $this->assertNotContains('LT', $operators);
        $this->assertNotContains('BT', $operators);
        $this->assertNotContains('NB', $operators);
    }

    /** @test */
    public function builds_nested_relations_tree_from_relation_paths()
    {
        config(['api-query-builder.model_options' => [
            TestModel::class => [
                'relations' => ['related.nested'],
            ],
        ]]);

        Schema::shouldReceive('hasTable')->andReturn(true);
        Schema::shouldReceive('getColumns')->andReturnUsing(function (string $table) {
            return match ($table) {
                'test' => [['name' => 'id', 'type' => 'int', 'nullable' => false]],
                'related' => [['name' => 'id', 'type' => 'int', 'nullable' => false]],
                'nested' => [['name' => 'id', 'type' => 'int', 'nullable' => false]],
                default => [],
            };
        });

        $schema = QueryBuilderSchema::forModel(new TestModel());

        $this->assertArrayHasKey('related', $schema['relations']);
        $this->assertArrayHasKey('nested', $schema['relations']['related']['relations']);
    }

    /** @test */
    public function uses_cast_operators_when_configured_for_column_type()
    {
        config(['api-query-builder.cast_operators' => [
            'integer' => [Equals::class, NotEquals::class],
        ]]);

        Schema::shouldReceive('hasTable')->andReturn(true);
        Schema::shouldReceive('getColumns')->with('test')->andReturn([
            ['name' => 'age', 'type' => 'integer', 'nullable' => false],
        ]);

        $schema = QueryBuilderSchema::forModel(new TestModel(), []);
        $operators = $schema['searchable_columns']['age']['operators'];

        $this->assertEquals(['EQ', 'NE'], $operators);
        $this->assertNotContains('LT', $operators);
        $this->assertNotContains('GT', $operators);
        $this->assertNotContains('LIKE', $operators);
    }

    /** @test */
    public function cast_operators_result_is_sorted_alphabetically()
    {
        config(['api-query-builder.cast_operators' => [
            'integer' => [NotEquals::class, Equals::class, GreaterThan::class],
        ]]);

        Schema::shouldReceive('hasTable')->andReturn(true);
        Schema::shouldReceive('getColumns')->with('test')->andReturn([
            ['name' => 'score', 'type' => 'integer', 'nullable' => false],
        ]);

        $schema = QueryBuilderSchema::forModel(new TestModel(), []);
        $operators = $schema['searchable_columns']['score']['operators'];

        $this->assertEquals(['EQ', 'GT', 'NE'], $operators);
    }

    /** @test */
    public function falls_back_to_normal_flow_when_type_not_in_cast_operators()
    {
        config(['api-query-builder.cast_operators' => [
            'boolean' => [Equals::class],
        ]]);

        Schema::shouldReceive('hasTable')->andReturn(true);
        Schema::shouldReceive('getColumns')->with('test')->andReturn([
            ['name' => 'age', 'type' => 'integer', 'nullable' => false],
        ]);

        $schema = QueryBuilderSchema::forModel(new TestModel(), []);
        $operators = $schema['searchable_columns']['age']['operators'];

        // Normal comparable-type flow for integer
        $this->assertContains('EQ', $operators);
        $this->assertContains('LT', $operators);
        $this->assertContains('GT', $operators);
        $this->assertNotContains('LIKE', $operators);
    }

    /** @test */
    public function uses_cast_operators_when_key_is_a_class_name()
    {
        $castClass = 'App\\Casts\\DynamicConfiguration';

        config(['api-query-builder.cast_operators' => [
            $castClass => [Equals::class, NotEquals::class],
        ]]);

        Schema::shouldReceive('hasTable')->andReturn(true);
        Schema::shouldReceive('getColumns')->with('test')->andReturn([
            // Type is the raw class name, as returned by getTypeFromCast() for custom casts
            ['name' => 'config', 'type' => $castClass, 'nullable' => false],
        ]);

        $schema = QueryBuilderSchema::forModel(new TestModel(), []);
        $operators = $schema['searchable_columns']['config']['operators'];

        $this->assertEquals(['EQ', 'NE'], $operators);
        $this->assertNotContains('LT', $operators);
        $this->assertNotContains('LIKE', $operators);
    }

    /** @test */
    public function throws_invalid_relation_exception_when_relation_does_not_exist()
    {
        Schema::shouldReceive('hasTable')->andReturn(true);
        Schema::shouldReceive('getColumns')->andReturnUsing(function (string $table) {
            return match ($table) {
                'test' => [['name' => 'id', 'type' => 'int', 'nullable' => false]],
                'related' => [['name' => 'id', 'type' => 'int', 'nullable' => false]],
                default => [],
            };
        });

        $this->expectException(InvalidRelationException::class);
        $this->expectExceptionMessage("Relation 'invalid' does not exist on model '");

        QueryBuilderSchema::forModel(new TestModel(), ['related.invalid']);
    }
}

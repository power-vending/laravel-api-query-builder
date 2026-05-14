<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PowerVending\LaravelApiQueryBuilder\Tests\{TestCase, TestModel};

class SchemaTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $model = new TestModel();
        $model->mergeCasts([
            'meta' => 'json',
        ]);

        $this->app->instance('schema.route.model', $model);

        config(['api-query-builder.resource_models' => [
            'tests' => 'schema.route.model',
        ]]);

        config(['api-query-builder.model_options' => [
            \PowerVending\LaravelApiQueryBuilder\Tests\CompanyModel::class => [
                'relations' => ['address'],
            ],
        ]]);

        Schema::dropIfExists('company_addresses');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('nested');
        Schema::dropIfExists('related');
        Schema::dropIfExists('test');

        Schema::create('company_addresses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('street')->nullable();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('address_id')->nullable();
            $table->string('name')->nullable();
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('profile_id')->nullable();
            $table->string('name')->nullable();
        });

        Schema::create('nested', function (Blueprint $table) {
            $table->increments('id');
            $table->string('description')->nullable();
        });

        Schema::create('related', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('nested_id')->nullable();
            $table->string('name')->nullable();
        });

        Schema::create('test', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('related_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->string('serial_number')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function tearDown(): void
    {
        Schema::dropIfExists('test');
        Schema::dropIfExists('related');
        Schema::dropIfExists('nested');
        Schema::dropIfExists('users');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('company_addresses');

        parent::tearDown();
    }

    /** @test */
    public function returns_schema_with_expected_top_level_structure()
    {
        $response = $this->getJson('/api-query-builder/tests/schema');

        $response->assertOk();
        $response->assertJsonPath('model', TestModel::class);
        $response->assertJsonPath('table', 'test');
        $response->assertJsonStructure([
            'model',
            'table',
            'searchable_columns',
            'sortable_columns',
            'relations',
        ]);
    }

    /** @test */
    public function returns_expected_comparable_operators_for_numeric_and_boolean_columns()
    {
        $payload = $this->getSchemaPayload();

        $this->assertArrayHasKey('id', $payload['searchable_columns']);
        $this->assertArrayHasKey('is_enabled', $payload['searchable_columns']);

        $idOperators = $payload['searchable_columns']['id']['operators'];
        $enabledOperators = $payload['searchable_columns']['is_enabled']['operators'];

        $expectedComparableOperators = ['NE', 'NB', 'LE', 'GE', 'BT', 'EQ', 'LT', 'GT'];

        $this->assertSame($expectedComparableOperators, $idOperators);
        $this->assertSame($expectedComparableOperators, $enabledOperators);
    }

    /** @test */
    public function returns_expected_text_operators_for_varchar_columns()
    {
        $payload = $this->getSchemaPayload();

        $this->assertArrayHasKey('serial_number', $payload['searchable_columns']);

        $serialOperators = $payload['searchable_columns']['serial_number']['operators'];
        $expectedTextOperators = ['STARTS_WITH', 'ENDS_WITH', 'LIKE', 'NE', 'EQ'];

        $this->assertSame($expectedTextOperators, $serialOperators);
    }

    /** @test */
    public function returns_expected_json_operators_for_json_columns()
    {
        $payload = $this->getSchemaPayload();

        $this->assertArrayHasKey('meta', $payload['searchable_columns']);

        $metaOperators = $payload['searchable_columns']['meta']['operators'];
        $expectedJsonOperators = ['STARTS_WITH', 'JSON_SEARCH', 'ENDS_WITH', 'LIKE', 'NE', 'EQ'];

        $this->assertSame($expectedJsonOperators, $metaOperators);
    }

    /** @test */
    public function returns_expected_nested_relations_from_model_options()
    {
        $payload = $this->getSchemaPayload();

        $this->assertArrayHasKey('related', $payload['relations']);
        $this->assertSame('related', $payload['relations']['related']['table']);
    }

    /** @test */
    public function merges_relations_query_param_with_configured_relations()
    {
        $response = $this->getJson('/api-query-builder/tests/schema?relations[]=related');
        $response->assertOk();

        $payload = $response->json();
        $this->assertIsArray($payload);

        $this->assertArrayHasKey('related', $payload['relations']);
        $this->assertArrayHasKey('tags', $payload['relations']);
        $this->assertArrayHasKey('company', $payload['relations']);
    }

    /** @test */
    public function expands_requested_relation_with_all_auto_discovered_descendants()
    {
        $response = $this->getJson('/api-query-builder/tests/schema?relations[]=company');
        $response->assertOk();

        $payload = $response->json();
        $this->assertIsArray($payload);

        $this->assertArrayHasKey('company', $payload['relations']);
        $this->assertArrayHasKey('address', $payload['relations']['company']['relations']);
        $this->assertArrayHasKey('users', $payload['relations']['company']['relations']);
    }

    /** @test */
    public function expands_only_first_level_for_each_node_in_requested_path()
    {
        $response = $this->getJson('/api-query-builder/tests/schema?relations[]=company.users');
        $response->assertOk();

        $payload = $response->json();
        $this->assertIsArray($payload);

        $this->assertArrayHasKey('company', $payload['relations']);
        $this->assertArrayHasKey('address', $payload['relations']['company']['relations']);
        $this->assertArrayHasKey('users', $payload['relations']['company']['relations']);
        $this->assertArrayHasKey('profile', $payload['relations']['company']['relations']['users']['relations']);
    }

    /** @test */
    public function auto_discovers_relations_when_not_configured_in_model_options()
    {
        config(['api-query-builder.model_options' => []]);

        $response = $this->getJson('/api-query-builder/tests/schema');
        $response->assertOk();

        $payload = $response->json();
        $this->assertIsArray($payload);

        $this->assertArrayHasKey('relations', $payload);
        $this->assertIsArray($payload['relations']);
        $this->assertArrayHasKey('related', $payload['relations']);
        $this->assertArrayHasKey('company', $payload['relations']);
        $this->assertArrayHasKey('tags', $payload['relations']);
    }

    private function getSchemaPayload(): array
    {
        $response = $this->getJson('/api-query-builder/tests/schema');
        $response->assertOk();

        $payload = $response->json();
        $this->assertIsArray($payload);

        return $payload;
    }

    /** @test */
    public function returns_not_found_for_non_exposed_resource()
    {
        $response = $this->getJson('/api-query-builder/missing/schema');

        $response->assertNotFound();
    }
}

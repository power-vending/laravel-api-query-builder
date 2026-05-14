<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\Http\Controllers;

use Illuminate\Support\Facades\Schema;
use PowerVending\LaravelApiQueryBuilder\Http\Controllers\SchemaController;
use PowerVending\LaravelApiQueryBuilder\Http\Requests\SchemaRequest;
use PowerVending\LaravelApiQueryBuilder\Tests\{TestCase, TestModel};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SchemaControllerTest extends TestCase
{
    /** @test */
    public function throws_not_found_when_resource_is_not_exposed()
    {
        config(['api-query-builder.resource_models' => []]);

        $controller = new SchemaController();
        $request = SchemaRequest::create('/schema', 'GET');

        $this->expectException(NotFoundHttpException::class);

        $controller->show($request, 'missing-resource');
    }

    /** @test */
    public function merges_requested_relations_with_default_relations_and_expands_requested_descendants()
    {
        config(['api-query-builder.resource_models' => [
            'tests' => 'schema.controller.test.model',
        ]]);

        config(['api-query-builder.model_options' => []]);

        $this->app->instance('schema.controller.test.model', new TestModel());

        Schema::shouldReceive('hasTable')->andReturn(true);
        Schema::shouldReceive('getColumns')->andReturnUsing(function (string $table) {
            return match ($table) {
                'test' => [['name' => 'id', 'type' => 'int', 'nullable' => false]],
                'related' => [['name' => 'id', 'type' => 'int', 'nullable' => false]],
                'nested' => [['name' => 'id', 'type' => 'int', 'nullable' => false]],
                'companies' => [['name' => 'id', 'type' => 'int', 'nullable' => false]],
                'company_addresses' => [['name' => 'id', 'type' => 'int', 'nullable' => false]],
                'users' => [['name' => 'id', 'type' => 'int', 'nullable' => false]],
                'user_profiles' => [['name' => 'id', 'type' => 'int', 'nullable' => false]],
                default => [],
            };
        });

        $controller = new SchemaController();
        $request = SchemaRequest::create('/schema', 'GET', ['relations' => ['company.users']]);

        $response = $controller->show($request, 'tests');
        $payload = $response->getData(true);

        $this->assertArrayHasKey('related', $payload['relations']);
        $this->assertArrayHasKey('company', $payload['relations']);
        $this->assertArrayHasKey('tags', $payload['relations']);
        $this->assertArrayHasKey('address', $payload['relations']['company']['relations']);
        $this->assertArrayHasKey('users', $payload['relations']['company']['relations']);
        $this->assertArrayHasKey('profile', $payload['relations']['company']['relations']['users']['relations']);
    }

    /** @test */
    public function auto_discovers_model_relations_when_not_configured()
    {
        config(['api-query-builder.resource_models' => [
            'tests' => 'schema.controller.test.model',
        ]]);

        config(['api-query-builder.model_options' => []]);

        $this->app->instance('schema.controller.test.model', new TestModel());

        Schema::shouldReceive('hasTable')->andReturn(true);
        Schema::shouldReceive('getColumns')->andReturnUsing(function (string $table) {
            return match ($table) {
                'test' => [['name' => 'id', 'type' => 'int', 'nullable' => false]],
                'related' => [['name' => 'id', 'type' => 'int', 'nullable' => false]],
                'companies' => [['name' => 'id', 'type' => 'int', 'nullable' => false]],
                default => [],
            };
        });

        $controller = new SchemaController();
        $request = SchemaRequest::create('/schema', 'GET');

        $response = $controller->show($request, 'tests');
        $payload = $response->getData(true);

        $this->assertArrayHasKey('related', $payload['relations']);
        $this->assertArrayHasKey('company', $payload['relations']);
        $this->assertArrayHasKey('tags', $payload['relations']);
    }
}

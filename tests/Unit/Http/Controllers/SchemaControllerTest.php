<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\Http\Controllers;

use PowerVending\LaravelApiQueryBuilder\Http\Controllers\SchemaController;
use PowerVending\LaravelApiQueryBuilder\Http\Requests\SchemaRequest;
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;
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
}

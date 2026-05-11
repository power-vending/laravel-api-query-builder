<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\Http\Requests;

use PowerVending\LaravelApiQueryBuilder\Http\Requests\SchemaRequest;
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;

class SchemaRequestTest extends TestCase
{
    /** @test */
    public function authorizes_request()
    {
        $request = new SchemaRequest();

        $this->assertTrue($request->authorize());
    }

    /** @test */
    public function exposes_expected_validation_rules()
    {
        $request = new SchemaRequest();

        $this->assertSame([
            'relations' => ['sometimes', 'array'],
            'relations.*' => ['string'],
        ], $request->rules());
    }
}

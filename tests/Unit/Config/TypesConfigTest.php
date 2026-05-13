<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\Config;

use Exception;
use PowerVending\LaravelApiQueryBuilder\Config\TypesConfig;
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;
use PowerVending\LaravelApiQueryBuilder\Types\{BooleanType, GenericType};

class TypesConfigTest extends TestCase
{
    /** @test */
    public function passes_on_valid_config()
    {
        $typesConfig = new TypesConfig();

        $this->assertNotEmpty($typesConfig->registered);
    }

    /** @test */
    public function throws_on_missing_config()
    {
        $this->expectException(Exception::class);

        config(['api-query-builder' => []]);

        new TypesConfig();
    }

    /** @test */
    public function it_returns_type_class()
    {
        $typesConfig = new TypesConfig();

        $this->assertEquals(
            new BooleanType(),
            $typesConfig->getTypeClassFromTypeName('boolean')
        );
    }

    /** @test */
    public function it_returns_generic_type_if_non_existing_type_is_given()
    {
        $typesConfig = new TypesConfig();

        $this->assertEquals(
            new GenericType(),
            $typesConfig->getTypeClassFromTypeName('test')
        );
    }

    /** @test */
    public function it_throws_exception_if_neither_generic_type_nor_given_type_exist()
    {
        $this->expectException(Exception::class);

        config(['api-query-builder.types' => []]);

        $typesConfig = new TypesConfig();

        $this->assertEquals(
            new GenericType(),
            $typesConfig->getTypeClassFromTypeName('test')
        );
    }
}

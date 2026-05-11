<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\Config;

use Exception;
use PowerVending\LaravelApiQueryBuilder\Config\OperatorsConfig;
use PowerVending\LaravelApiQueryBuilder\SearchCallbacks\Equals;
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;

class OperatorsConfigTest extends TestCase
{
    /** @test */
    public function passes_on_valid_config()
    {
        $operators = new OperatorsConfig();

        $this->assertNotEmpty($operators->registered);
    }

    /** @test */
    public function throws_on_missing_config()
    {
        $this->expectException(Exception::class);

        config(['api-query-builder' => []]);

        new OperatorsConfig();
    }

    /** @test */
    public function returns_registered_operators()
    {
        $operatorsConfig = new OperatorsConfig();

        $expected = ['STARTS_WITH:', 'JSON_SEARCH:', 'ENDS_WITH:', 'LIKE:', 'NE:', 'NB:', 'LE:', 'GE:', 'BT:', 'EQ:', 'LT:', 'GT:'];

        $this->assertEquals($expected, $operatorsConfig->getOperators());
    }

    /** @test */
    public function returns_class_from_given_operator()
    {
        $operatorsConfig = new OperatorsConfig();

        $this->assertEquals(Equals::class, $operatorsConfig->getCallbackClassFromOperator('EQ:'));
    }

    /** @test */
    public function throws_exception_on_non_existing_operator()
    {
        $this->expectException(Exception::class);

        $operatorsConfig = new OperatorsConfig();

        $operatorsConfig->getCallbackClassFromOperator('123');
    }
}

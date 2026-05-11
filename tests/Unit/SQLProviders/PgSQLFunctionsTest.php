<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\SQLProviders;

use PowerVending\LaravelApiQueryBuilder\SQLProviders\{PgSQLFunctions, SQLFunctions};
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;

class PgSQLFunctionsTest extends TestCase
{
    public SQLFunctions $functions;

    public function setUp(): void
    {
        parent::setUp();

        $this->functions = new PgSQLFunctions();
    }

    public function test_it_should_return_the_year_function()
    {
        $query = $this->functions::year('column');
        $this->assertEquals('EXTRACT(YEAR FROM column)', $query);
    }

    public function test_it_should_return_the_month_function()
    {
        $query = $this->functions::month('column');
        $this->assertEquals('EXTRACT(MONTH FROM column)', $query);
    }

    public function test_it_should_return_the_day_function()
    {
        $query = $this->functions::day('column');
        $this->assertEquals('EXTRACT(DAY FROM column)', $query);
    }
}

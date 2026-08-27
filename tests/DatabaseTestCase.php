<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use PowerVending\LaravelApiQueryBuilder\Support\QualifyingQueryBuilder;

/**
 * Test case backed by a real in-memory SQLite schema, needed by anything that
 * inspects columns (Schema::hasColumn) or actually runs the generated SQL.
 */
abstract class DatabaseTestCase extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // The schema is rebuilt per test, so the static column cache must not survive.
        QualifyingQueryBuilder::flushColumnCache();

        $this->createSchema();
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function createSchema(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('terminals', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('serial')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('tenant_id')->nullable();
        });

        Schema::create('configurations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        // 'configuration_id' lives only here, never on 'configurations'.
        Schema::create('companies_has_configurations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('configuration_id');
        });
    }
}

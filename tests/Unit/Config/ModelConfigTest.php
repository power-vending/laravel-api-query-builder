<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\Config;

use App\Casts\{DynamicConfiguration, DynamicProperty};
use Illuminate\Database\Eloquent\Model;
use Mockery;
use PowerVending\LaravelApiQueryBuilder\Config\ModelConfig;
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;

class ModelConfigTest extends TestCase
{
    protected Model $model;

    public function setUp(): void
    {
        parent::setUp();

        $this->model = Mockery::mock(Model::class);
    }

    /** @test */
    public function model_has_config()
    {
        config(['api-query-builder.model_options' => [
            get_class($this->model) => ['random_config' => '123'],
        ]]);

        $modelConfig = new ModelConfig($this->model);

        $this->assertTrue($modelConfig->hasConfig());
    }

    /** @test */
    public function has_returns_config_set()
    {
        config(['api-query-builder.model_options' => [
            get_class($this->model) => ['returns' => '123'],
        ]]);

        $modelConfig = new ModelConfig($this->model);

        $this->assertEquals(['123'], $modelConfig->getReturns());
    }

    /** @test */
    public function has_default_returns_config_set()
    {
        $modelConfig = new ModelConfig($this->model);

        $this->assertEquals(['*'], $modelConfig->getReturns());
    }

    /** @test */
    public function has_order_by_config_set()
    {
        config(['api-query-builder.model_options' => [
            get_class($this->model) => [
                'order_by' => [
                    'attribute' => 'asc',
                ],
            ],
        ]]);

        $modelConfig = new ModelConfig($this->model);

        $this->assertEquals(['attribute=asc'], $modelConfig->getOrderBy());
    }

    /** @test */
    public function has_default_order_by_config_set()
    {
        $modelConfig = new ModelConfig($this->model);

        $this->assertEquals([], $modelConfig->getOrderBy());
    }

    /** @test */
    public function resolves_dynamic_configuration_cast_to_custom_type()
    {
        $this->model->shouldReceive('getCasts')->andReturn([
            'value' => DynamicConfiguration::class,
        ]);

        $modelConfig = new ModelConfig($this->model);

        $this->assertEquals(DynamicConfiguration::class, $modelConfig->getTypeFromCast('value'));
    }

    /** @test */
    public function resolves_dynamic_property_cast_to_custom_type()
    {
        $this->model->shouldReceive('getCasts')->andReturn([
            'value' => DynamicProperty::class,
        ]);

        $modelConfig = new ModelConfig($this->model);

        $this->assertEquals(DynamicProperty::class, $modelConfig->getTypeFromCast('value'));
    }
}

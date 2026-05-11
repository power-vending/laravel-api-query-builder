<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\RequestParameters;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use PowerVending\LaravelApiQueryBuilder\Config\ModelConfig;
use PowerVending\LaravelApiQueryBuilder\RequestParameters\RelationsParameter;
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;

class RelationsParameterTest extends TestCase
{
    protected Builder $builder;

    protected ModelConfig $modelConfig;

    public function setUp(): void
    {
        parent::setUp();

        $this->builder = app(Builder::class);

        $this->modelConfig = Mockery::mock(ModelConfig::class);
    }

    /** @test */
    public function has_a_name()
    {
        $relationsParameter = new RelationsParameter([], $this->builder, $this->modelConfig);

        $this->assertEquals('relations', $relationsParameter::getParameterName());
    }

    /** @test */
    public function accepts_valid_arguments()
    {
        $relationsParameter = new RelationsParameter(
            ['attribute1', 'attribute2'],
            $this->builder,
            $this->modelConfig
        );
        $relationsParameter->run();

        $this->assertTrue(true);
    }

    /** @test */
    public function rejects_empty_argument()
    {
        $this->expectException(Exception::class);

        $relationsParameter = new RelationsParameter([], $this->builder, $this->modelConfig);
        $relationsParameter->run();
    }

    /** @test */
    public function relations_do_not_produce_query_like_this_so_this_test_is_useless()
    {
        $relationsParameter = new RelationsParameter(
            ['attribute1', 'attribute2'],
            $this->builder,
            $this->modelConfig
        );
        $relationsParameter->run();

        $query = 'select *';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function accepts_relation_with_configuration_object()
    {
        $relationsParameter = new RelationsParameter(
            [
                'simpleRelation',
                [
                    'complexRelation' => [
                        'order_by' => ['created_at' => 'desc'],
                        'search' => ['status' => 'EQ:active']
                    ]
                ]
            ],
            $this->builder,
            $this->modelConfig
        );

        $relationsParameter->run();

        $this->assertTrue(true);
    }

    /** @test */
    public function accepts_multiple_configured_relations()
    {
        $relationsParameter = new RelationsParameter(
            [
                [
                    'orders' => [
                        'order_by' => ['created_at' => 'desc'],
                        'limit' => 5
                    ]
                ],
                [
                    'reviews' => [
                        'search' => ['rating' => 'GE:4'],
                        'order_by' => ['helpful_votes' => 'desc']
                    ]
                ]
            ],
            $this->builder,
            $this->modelConfig
        );

        $relationsParameter->run();

        $this->assertTrue(true);
    }
}

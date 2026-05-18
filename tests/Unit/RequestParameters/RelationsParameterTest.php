<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\RequestParameters;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use PowerVending\LaravelApiQueryBuilder\Config\ModelConfig;
use PowerVending\LaravelApiQueryBuilder\Exceptions\InvalidRelationException;
use PowerVending\LaravelApiQueryBuilder\RequestParameters\RelationsParameter;
use PowerVending\LaravelApiQueryBuilder\Tests\{TestCase, TestModel};

class RelationsParameterTest extends TestCase
{
    protected Builder $builder;

    protected ModelConfig $modelConfig;

    public function setUp(): void
    {
        parent::setUp();

        $this->builder = app(Builder::class);
        $this->builder->setModel(new TestModel());

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
            ['tags', 'related'],
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
            ['tags', 'related'],
            $this->builder,
            $this->modelConfig
        );
        $relationsParameter->run();

        $query = 'select * from "test"';

        $this->assertEquals($query, $this->builder->toSql());
    }

    /** @test */
    public function accepts_relation_with_configuration_object()
    {
        $relationsParameter = new RelationsParameter(
            [
                'related',
                [
                    'tags' => [
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
                    'tags' => [
                        'order_by' => ['created_at' => 'desc'],
                        'limit' => 5
                    ]
                ],
                [
                    'related' => [
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

    /** @test */
    public function throws_on_invalid_relation()
    {
        $this->expectException(InvalidRelationException::class);

        $relationsParameter = new RelationsParameter(
            ['unknown_relation'],
            $this->builder,
            $this->modelConfig
        );

        $relationsParameter->run();
    }

    /** @test */
    public function throws_on_forbidden_relation_defined_in_model_options()
    {
        config(['api-query-builder.model_options' => [
            TestModel::class => [
                'forbidden_relations' => ['tags'],
            ],
        ]]);

        $this->expectException(InvalidRelationException::class);

        $relationsParameter = new RelationsParameter(
            ['tags'],
            $this->builder,
            $this->modelConfig
        );

        $relationsParameter->run();
    }
}

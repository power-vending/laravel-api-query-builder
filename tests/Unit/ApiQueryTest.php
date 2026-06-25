<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\Parsers;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use PowerVending\LaravelApiQueryBuilder\ApiQuery;
use PowerVending\LaravelApiQueryBuilder\Config\ModelConfig;
use PowerVending\LaravelApiQueryBuilder\Exceptions\InvalidRelationException;
use PowerVending\LaravelApiQueryBuilder\Tests\{TestCase, TestModel};

class ApiQueryTest extends TestCase
{
    protected Builder $builder;

    protected ModelConfig $modelConfig;

    public function setUp(): void
    {
        parent::setUp();

        $this->builder = app(Builder::class);
        $this->builder->setModel(new TestModel());
    }

    /** @test */
    public function throws_on_existing_models()
    {
        $this->expectException(Exception::class);

        $this->builder->getModel()->exists = true;

        new ApiQuery($this->builder, []);
    }

    /** @test */
    public function searches_single_attribute()
    {
        $input = [
            'search' => [
                'att1' => 'EQ:1',
            ],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = 'select * from "test" where ((("test"."att1" in (?))))';

        $this->assertEquals($sql, $this->builder->toSql());
    }

    /** @test */
    public function searches_multiple_attributes()
    {
        $input = [
            'search' => [
                'att1' => 'EQ:1;2;3',
                'att2' => 'EQ:4;5;6',
            ],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = 'select * from "test" where ((("test"."att1" in (?, ?, ?))) and (("test"."att2" in (?, ?, ?))))';

        $this->assertEquals($sql, $this->builder->toSql());
    }

    /** @test */
    public function searches_negated_attributes()
    {
        $input = [
            'search' => [
                'att1' => 'EQ:1;!2;!3',
            ],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = 'select * from "test" where ((("test"."att1" in (?) and "test"."att1" not in (?, ?))))';

        $this->assertEquals($sql, $this->builder->toSql());
    }

    /** @test */
    public function searches_wildcard_attributes()
    {
        $input = [
            'search' => [
                'att1' => 'EQ:1;%2;3%',
            ],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = 'select * from "test" where ((("test"."att1" LIKE ? and "test"."att1" LIKE ? and "test"."att1" in (?))))';

        $this->assertEquals($sql, $this->builder->toSql());
    }

    /** @test */
    public function searches_with_all_operators()
    {
        $input = [
            'search' => [
                'att1' => 'EQ:1',
                'att2' => 'LT:1',
                'att3' => 'LE:1',
                'att4' => 'GT:1',
                'att5' => 'GE:1',
                'att6' => 'BT:1;2',
                'att7' => 'NB:1;2',
                'att8' => 'NE:1',
            ],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = 'select * from "test" where ((("test"."att1" in (?))) and (("test"."att2" < ?)) and (("test"."att3" <= ?)) and (("test"."att4" > ?)) and (("test"."att5" >= ?)) and (("test"."att6" between ? and ?)) and (("test"."att7" not between ? and ?)) and (("test"."att8" not in (?))))';

        $this->assertEquals($sql, $this->builder->toSql());
    }

    /** @test */
    public function searches_with_or_micro_operator()
    {
        $input = [
            'search' => [
                'id' => 'EQ:1||EQ:2',
            ],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = 'select * from "test" where ((("test"."id" in (?)) or ("test"."id" in (?))))';

        $this->assertEquals($sql, $this->builder->toSql());
    }

    /** @test */
    public function searches_with_and_micro_operator()
    {
        $input = [
            'search' => [
                'id' => 'EQ:1&&EQ:2',
            ],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = 'select * from "test" where ((("test"."id" in (?) and "test"."id" in (?))))';

        $this->assertEquals($sql, $this->builder->toSql());
    }

    /** @test */
    public function selects_only_given_attributes()
    {
        $input = [
            'returns' => ['id', 'other'],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = 'select "id", "other" from "test"';

        $this->assertEquals($sql, $this->builder->toSql());
    }

    /** @test */
    public function selects_all_attributes_except_given_ones()
    {
        config(['api-query-builder.model_options' => [
            TestModel::class => [
                'returns' => ['id', 'other', 'att1'],
            ],
        ]]);

        $input = [
            'excepts' => ['other'],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = 'select "id", "att1" from "test"';

        $this->assertEquals($sql, $this->builder->toSql());
    }

    /** @test */
    public function orders_by_attributes()
    {
        $input = [
            'order_by' => [
                'att1' => 'asc',
                'att2' => 'desc',
                'att3',
            ],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = 'select * from "test" order by "test"."att1" asc, "test"."att2" desc, "test"."att3" asc';

        $this->assertEquals($sql, $this->builder->toSql());
    }

    /** @test */
    public function groups_by_attributes()
    {
        $input = [
            'group_by' => ['att1', 'att2'],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = 'select * from "test" group by "att1", "att2"';

        $this->assertEquals($sql, $this->builder->toSql());
    }

    /** @test */
    public function limits_and_offsets_results()
    {
        $input = [
            'limit' => 5,
            'offset' => 10,
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = 'select * from "test" limit 5 offset 10';

        $this->assertEquals($sql, $this->builder->toSql());
    }

    /** @test */
    public function counts_results()
    {
        $input = [
            'count' => true,
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = 'select count(*) as count from "test"';

        $this->assertEquals($sql, $this->builder->toSql());
    }

    /** @test */
    public function uses_top_level_logical_operator_for_complex_queries()
    {
        $input = [
            'search' => [
                '||' => [
                    'att1' => 'EQ:1',
                    'att2' => 'EQ:1',
                ],
            ],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = 'select * from "test" where ((("test"."att1" in (?))) or (("test"."att2" in (?))))';

        $this->assertEquals($sql, $this->builder->toSql());
    }

    /** @test */
    public function uses_top_level_logical_operator_for_complex_recursive_queries()
    {
        $input = [
            'search' => [
                '&&' => [
                    '||' => [
                        'att1' => 'EQ:1',
                        'att2' => 'EQ:1',
                    ],
                    'att3' => 'EQ:1',
                    'att4' => 'EQ:1',
                ],
            ],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = 'select * from "test" where ((("test"."att1" in (?))) or (("test"."att2" in (?))) and (("test"."att3" in (?))) and (("test"."att4" in (?))))';

        $this->assertEquals($sql, $this->builder->toSql());
    }

    /** @test */
    public function can_recurse_absurdly_deep()
    {
        $input = [
            'search' => [
                '||' => [
                    '&&' => [
                        [
                            '||' => [
                                [
                                    'id' => 'EQ:2||EQ:3',
                                    'name' => 'EQ:foo',
                                ],
                                [
                                    'id' => 'EQ:1',
                                    'name' => 'EQ:foo%&&EQ:%bar',
                                ],
                            ],
                        ],
                        [
                            'we' => 'EQ:cool',
                        ],
                    ],
                    'love' => 'LT:3',
                    'recursion' => 'EQ:rrr',
                ],
            ],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = 'select * from "test" where ((((("test"."id" in (?)) or ("test"."id" in (?))) and (("test"."name" in (?)))) or ((("test"."id" in (?))) and (("test"."name" LIKE ? and "test"."name" LIKE ?)))) and ((("test"."we" in (?)))) or (("test"."love" < ?)) or (("test"."recursion" in (?))))';

        $this->assertEquals($sql, $this->builder->toSql());
    }

    /** @test */
    public function qualifies_main_model_search_columns_when_ordering_by_relation()
    {
        $input = [
            'search' => [
                'created_at' => 'BT:2026-06-12 00:00:00;2026-06-12 23:59:59',
            ],
            'order_by' => [
                'company.id' => 'asc',
            ],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = $this->builder->toSql();

        $this->assertStringContainsString('left join "companies"', strtolower($sql));
        $this->assertStringContainsString('"test"."created_at" between ? and ?', $sql);
    }

    /** @test */
    public function throws_on_invalid_relation_in_search_filter()
    {
        $this->expectException(InvalidRelationException::class);

        $input = [
            'search' => [
                'unknown_relation' => [
                    'search' => [
                        'id' => 'EQ:1',
                    ],
                ],
            ],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();
    }

    /** @test */
    public function throws_on_invalid_relation_in_dot_notation_search_filter()
    {
        $this->expectException(InvalidRelationException::class);

        $input = [
            'search' => [
                'aaa.description' => 'EQ:sed',
            ],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();
    }

    /** @test */
    public function can_search_by_relation_column_using_dot_notation()
    {
        $input = [
            'search' => [
                'related.description' => 'EQ:sed',
            ],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = $this->builder->toSql();

        $this->assertStringContainsString('exists (select * from "related"', $sql);
        $this->assertStringContainsString('"description" in (?)', $sql);
    }

    /** @test */
    public function throws_on_invalid_relation_in_doesnt_have_relations()
    {
        $this->expectException(InvalidRelationException::class);

        $input = [
            'doesnt_have_relations' => [
                'unknown_relation',
            ],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();
    }

    /** @test */
    public function can_query_by_many_to_many_relationships()
    {
        $input = [
            'search' => [
                'tags' => [
                    "search" => [
                        "id" => "EQ:1",
                    ],
                ],
            ],
        ];

        $jsonQuery = new ApiQuery($this->builder, $input);
        $jsonQuery->search();

        $sql = 'select * from "test" where (exists (select * from "tags" inner join "taggables" on "tags"."id" = "taggables"."tag_id" where "test"."id" = "taggables"."taggable_id" and "taggables"."taggable_type" = ? and ((("tags"."id" in (?))))))';

        $this->assertEquals($sql, $this->builder->toSql());
    }
}

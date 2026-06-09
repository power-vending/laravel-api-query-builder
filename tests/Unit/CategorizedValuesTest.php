<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\Parsers;

use Mockery;
use PowerVending\LaravelApiQueryBuilder\{CategorizedValues, SearchParser};
use PowerVending\LaravelApiQueryBuilder\Tests\Fixtures\RecordingType;
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;
use PowerVending\LaravelApiQueryBuilder\Types\{BooleanType, GenericType};

class CategorizedValuesTest extends TestCase
{
    protected SearchParser $searchParser;

    public function setUp(): void
    {
        parent::setUp();

        $this->searchParser = Mockery::mock(SearchParser::class);

        $this->searchParser->type = 'string';
        $this->searchParser->shouldReceive('getOperator')->andReturn('EQ:');
    }

    /** @test */
    public function has_null_value()
    {
        $this->searchParser->values = ['123', '456', 'null', '789'];

        $categorizedValues = new CategorizedValues($this->searchParser);

        $this->assertTrue($categorizedValues->null);
    }

    /** @test */
    public function has_no_null_value()
    {
        $this->searchParser->values = ['123', '456', '789'];

        $categorizedValues = new CategorizedValues($this->searchParser);

        $this->assertFalse($categorizedValues->null);
    }

    /** @test */
    public function has_not_null_value()
    {
        $this->searchParser->values = ['123', '456', '!null', '789'];

        $categorizedValues = new CategorizedValues($this->searchParser);

        $this->assertTrue($categorizedValues->notNull);
    }

    /** @test */
    public function has_no_not_null_value()
    {
        $this->searchParser->values = ['123', '456', '789'];

        $categorizedValues = new CategorizedValues($this->searchParser);

        $this->assertFalse($categorizedValues->notNull);
    }

    /** @test */
    public function has_not_values()
    {
        $this->searchParser->values = ['123', '!456', '!789'];

        $categorizedValues = new CategorizedValues($this->searchParser);

        $this->assertEquals(['456', '789'], $categorizedValues->not);
    }

    /** @test */
    public function has_not_like_values()
    {
        $this->searchParser->values = ['123', '!%456', '!789%'];

        $categorizedValues = new CategorizedValues($this->searchParser);

        $this->assertEquals(['%456', '789%'], $categorizedValues->notLike);
    }

    /** @test */
    public function has_and_values()
    {
        $this->searchParser->values = ['123', '456', '!789'];

        $categorizedValues = new CategorizedValues($this->searchParser);

        $this->assertEquals(['123', '456'], $categorizedValues->and);
    }

    /** @test */
    public function has_and_like_values()
    {
        $this->searchParser->values = ['123', '%456', '789%'];

        $categorizedValues = new CategorizedValues($this->searchParser);

        $this->assertEquals(['%456', '789%'], $categorizedValues->andLike);
    }

    /** @test */
    public function passes_search_parser_operator_to_prepare()
    {
        RecordingType::reset();

        config(['api-query-builder.types' => [
            GenericType::class,
            BooleanType::class,
            RecordingType::class,
        ]]);

        foreach (['LIKE:', 'EQ:'] as $operator) {
            RecordingType::reset();

            $searchParser = Mockery::mock(SearchParser::class);
            $searchParser->type = 'recording';
            $searchParser->values = ['terminal'];
            $searchParser->shouldReceive('getOperator')->andReturn($operator);

            new CategorizedValues($searchParser);

            $this->assertSame($operator, RecordingType::$prepareCalls[0]['operator']);
        }
    }

    /** @test */
    public function format_prepares_all_four_buckets_in_order()
    {
        RecordingType::reset();

        config(['api-query-builder.types' => [
            GenericType::class,
            BooleanType::class,
            RecordingType::class,
        ]]);

        $this->searchParser->type = 'recording';
        $this->searchParser->values = ['and1', '%andLike%', '!not1', '!%notLike%'];

        new CategorizedValues($this->searchParser);

        $this->assertCount(4, RecordingType::$prepareCalls);
        $this->assertSame(['and1'], RecordingType::$prepareCalls[0]['values']);
        $this->assertSame(['%andLike%'], RecordingType::$prepareCalls[1]['values']);
        $this->assertSame(['not1'], RecordingType::$prepareCalls[2]['values']);
        $this->assertSame(['%notLike%'], RecordingType::$prepareCalls[3]['values']);
    }
}

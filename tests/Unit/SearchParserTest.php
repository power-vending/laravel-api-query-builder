<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\Parsers;

use Mockery;
use PowerVending\LaravelApiQueryBuilder\Config\{ModelConfig, OperatorsConfig};
use PowerVending\LaravelApiQueryBuilder\SearchParser;
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;

class SearchParserTest extends TestCase
{
    protected SearchParser $searchParser;

    public function setUp(): void
    {
        parent::setUp();

        $modelConfig = Mockery::mock(ModelConfig::class);

        $modelConfig->shouldReceive('getForbidden')->andReturn([]);
        $modelConfig->shouldReceive('getModelColumns')->andReturn([
            'test' => 'string',
        ]);
        $modelConfig->shouldReceive('getTypeFromCast')->andReturn(null);
        $modelConfig->shouldReceive('isPrimaryKey')->andReturn(false);

        $this->searchParser = new SearchParser(
            $modelConfig,
            new OperatorsConfig(),
            'test',
            'EQ:123;456'
        );
    }

    /** @test */
    public function it_extracts_column()
    {
        $this->assertEquals('test', $this->searchParser->column);
    }

    /** @test */
    public function it_extracts_values_from_argument_splitting_by_separator()
    {
        $this->assertEquals(['123', '456'], $this->searchParser->values);
    }

    /** @test */
    public function it_extracts_column_types()
    {
        $this->assertEquals('string', $this->searchParser->type);
    }

    /** @test */
    public function it_extracts_operator_from_argument()
    {
        $this->assertEquals('EQ:', $this->searchParser->operator);
    }

    /** @test */
    public function it_prioritizes_type_from_cast_over_schema_column_type()
    {
        $modelConfig = Mockery::mock(ModelConfig::class);

        $modelConfig->shouldReceive('getForbidden')->andReturn([]);
        $modelConfig->shouldReceive('getModelColumns')->andReturn([
            'value' => 'longtext',
        ]);
        $modelConfig->shouldReceive('getTypeFromCast')->with('value')->andReturn('json');
        $modelConfig->shouldReceive('isPrimaryKey')->andReturn(false);

        $searchParser = new SearchParser(
            $modelConfig,
            new OperatorsConfig(),
            'value',
            'EQ:{"enabled":true}'
        );

        $this->assertEquals('json', $searchParser->type);
    }
}

<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\Parsers;

use Mockery;
use PowerVending\LaravelApiQueryBuilder\Config\{ModelConfig, OperatorsConfig};
use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;
use PowerVending\LaravelApiQueryBuilder\SearchCallbacks\{Equals, GreaterThan};
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

    /** @test */
    public function it_accepts_operator_allowed_by_cast_operators_config()
    {
        config(['api-query-builder.cast_operators' => [
            'boolean' => [Equals::class],
        ]]);

        $modelConfig = Mockery::mock(ModelConfig::class);
        $modelConfig->shouldReceive('getForbidden')->andReturn([]);
        $modelConfig->shouldReceive('getModelColumns')->andReturn(['active' => 'boolean']);
        $modelConfig->shouldReceive('getTypeFromCast')->with('active')->andReturn('boolean');
        $modelConfig->shouldReceive('isPrimaryKey')->andReturn(false);

        $parser = new SearchParser($modelConfig, new OperatorsConfig(), 'active', 'EQ:1');

        $this->assertEquals('EQ:', $parser->operator);
        $this->assertEquals('boolean', $parser->type);
    }

    /** @test */
    public function it_throws_when_operator_is_forbidden_by_cast_operators_config()
    {
        config(['api-query-builder.cast_operators' => [
            'boolean' => [Equals::class],
        ]]);

        $modelConfig = Mockery::mock(ModelConfig::class);
        $modelConfig->shouldReceive('getForbidden')->andReturn([]);
        $modelConfig->shouldReceive('getModelColumns')->andReturn(['active' => 'boolean']);
        $modelConfig->shouldReceive('getTypeFromCast')->with('active')->andReturn('boolean');
        $modelConfig->shouldReceive('isPrimaryKey')->andReturn(false);

        $this->expectException(ApiQueryBuilderException::class);
        $this->expectExceptionMessage("Operator 'GT:' is not allowed for cast type 'boolean' on column 'active'.");

        new SearchParser($modelConfig, new OperatorsConfig(), 'active', 'GT:1');
    }

    /** @test */
    public function it_accepts_operator_allowed_when_cast_key_is_a_class_name()
    {
        $castClass = 'App\\Casts\\DynamicConfiguration';

        config(['api-query-builder.cast_operators' => [
            $castClass => [Equals::class],
        ]]);

        $modelConfig = Mockery::mock(ModelConfig::class);
        $modelConfig->shouldReceive('getForbidden')->andReturn([]);
        $modelConfig->shouldReceive('getModelColumns')->andReturn(['config' => 'longtext']);
        $modelConfig->shouldReceive('getTypeFromCast')->with('config')->andReturn($castClass);
        $modelConfig->shouldReceive('isPrimaryKey')->andReturn(false);

        $parser = new SearchParser($modelConfig, new OperatorsConfig(), 'config', 'EQ:somevalue');

        $this->assertEquals('EQ:', $parser->operator);
        $this->assertEquals($castClass, $parser->type);
    }

    /** @test */
    public function it_throws_when_operator_forbidden_and_cast_key_is_a_class_name()
    {
        $castClass = 'App\\Casts\\DynamicConfiguration';

        config(['api-query-builder.cast_operators' => [
            $castClass => [Equals::class],
        ]]);

        $modelConfig = Mockery::mock(ModelConfig::class);
        $modelConfig->shouldReceive('getForbidden')->andReturn([]);
        $modelConfig->shouldReceive('getModelColumns')->andReturn(['config' => 'longtext']);
        $modelConfig->shouldReceive('getTypeFromCast')->with('config')->andReturn($castClass);
        $modelConfig->shouldReceive('isPrimaryKey')->andReturn(false);

        $this->expectException(ApiQueryBuilderException::class);
        $this->expectExceptionMessage("Operator 'GT:' is not allowed for cast type '$castClass' on column 'config'.");

        new SearchParser($modelConfig, new OperatorsConfig(), 'config', 'GT:somevalue');
    }

    /** @test */
    public function it_does_not_restrict_operators_when_cast_type_not_in_cast_operators_config()
    {
        config(['api-query-builder.cast_operators' => [
            'boolean' => [Equals::class],
        ]]);

        $modelConfig = Mockery::mock(ModelConfig::class);
        $modelConfig->shouldReceive('getForbidden')->andReturn([]);
        $modelConfig->shouldReceive('getModelColumns')->andReturn(['score' => 'integer']);
        $modelConfig->shouldReceive('getTypeFromCast')->with('score')->andReturn('integer');
        $modelConfig->shouldReceive('isPrimaryKey')->andReturn(false);

        // 'integer' is not in cast_operators, so GT: should be accepted normally
        $parser = new SearchParser($modelConfig, new OperatorsConfig(), 'score', 'GT:10');

        $this->assertEquals('GT:', $parser->operator);
        $this->assertEquals('integer', $parser->type);
    }
}

<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Unit\SearchCallbacks;

use PowerVending\LaravelApiQueryBuilder\SearchCallbacks\{
    AbstractCallback,
    Between,
    EndsWith,
    Equals,
    GreaterThan,
    GreaterThanOrEqual,
    JsonSearch,
    LessThan,
    LessThanOrEqual,
    Like,
    NotBetween,
    NotEquals,
    StartsWith
};
use PowerVending\LaravelApiQueryBuilder\Tests\TestCase;

class OperatorSupportMatrixTest extends TestCase
{
    /** @test */
    public function abstract_callback_defaults_are_kept()
    {
        $this->assertTrue(AbstractCallback::supportsTextTypes());
        $this->assertTrue(AbstractCallback::supportsComparableTypes());
        $this->assertFalse(AbstractCallback::supportsJsonTypes());
    }

    /** @test */
    public function comparable_operators_do_not_support_text_types()
    {
        $this->assertFalse(Between::supportsTextTypes());
        $this->assertFalse(NotBetween::supportsTextTypes());
        $this->assertFalse(GreaterThan::supportsTextTypes());
        $this->assertFalse(GreaterThanOrEqual::supportsTextTypes());
        $this->assertFalse(LessThan::supportsTextTypes());
        $this->assertFalse(LessThanOrEqual::supportsTextTypes());
    }

    /** @test */
    public function text_operators_do_not_support_comparable_types()
    {
        $this->assertFalse(Like::supportsComparableTypes());
        $this->assertFalse(StartsWith::supportsComparableTypes());
        $this->assertFalse(EndsWith::supportsComparableTypes());
    }

    /** @test */
    public function equals_and_not_equals_support_json_types()
    {
        $this->assertTrue(Equals::supportsJsonTypes());
        $this->assertTrue(NotEquals::supportsJsonTypes());
    }

    /** @test */
    public function json_search_support_matrix_is_strict()
    {
        $this->assertFalse(JsonSearch::supportsTextTypes());
        $this->assertFalse(JsonSearch::supportsComparableTypes());
        $this->assertTrue(JsonSearch::supportsJsonTypes());
    }
}

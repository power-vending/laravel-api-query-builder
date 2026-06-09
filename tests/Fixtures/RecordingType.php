<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Fixtures;

use PowerVending\LaravelApiQueryBuilder\SearchParserInterface;
use PowerVending\LaravelApiQueryBuilder\Types\AbstractType;

class RecordingType extends AbstractType
{
    /** @var array<int, array{operator: string|null, values: array}> */
    public static array $prepareCalls = [];

    public static function name(): string
    {
        return 'recording';
    }

    public function prepare(array $values, ?SearchParserInterface $searchParser = null): array
    {
        self::$prepareCalls[] = [
            'operator' => $searchParser?->getOperator(),
            'values' => $values,
        ];

        return $values;
    }

    public static function reset(): void
    {
        self::$prepareCalls = [];
    }
}

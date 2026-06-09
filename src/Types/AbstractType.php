<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Types;

use PowerVending\LaravelApiQueryBuilder\SearchParserInterface;

abstract class AbstractType
{
    /**
     * Name of the type as it is used within Laravel migrations.
     *
     * @return string
     */
    abstract public static function name(): string;

    /**
     * Prepare/transform values for query if needed.
     *
     * @param  array  $values
     * @param  SearchParserInterface|null  $searchParser
     * @return array
     */
    public function prepare(array $values, ?SearchParserInterface $searchParser = null): array
    {
        return $values;
    }
}

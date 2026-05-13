<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Types;

class GenericType extends AbstractType
{
    public static function name(): string
    {
        return 'generic';
    }
}

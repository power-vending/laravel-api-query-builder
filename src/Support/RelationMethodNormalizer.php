<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Support;

use Illuminate\Support\Str;

class RelationMethodNormalizer
{
    public static function normalize(string $segment): ?string
    {
        $normalized = trim($segment);

        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, '_')) {
            return null;
        }

        return Str::camel($normalized);
    }
}
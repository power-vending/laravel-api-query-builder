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

        $leadingUnderscoresCount = strspn($normalized, '_');
        $prefix = substr($normalized, 0, $leadingUnderscoresCount);
        $core = substr($normalized, $leadingUnderscoresCount);

        if ($core === '') {
            return null;
        }

        return $prefix . Str::camel($core);
    }
}
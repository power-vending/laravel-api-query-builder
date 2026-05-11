<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\SearchCallbacks;

use Illuminate\Database\Eloquent\Builder;
use PowerVending\LaravelApiQueryBuilder\CategorizedValues;

class LessThanOrEqual extends AbstractCallback
{
    public static function operator(): string
    {
        return 'LE:';
    }

    public static function supportsTextTypes(): bool
    {
        return false;
    }

    public function execute(Builder $builder, string $column, CategorizedValues $values): void
    {
        $this->lessOrMoreCallback($builder, $column, $values, '<=');
    }
}

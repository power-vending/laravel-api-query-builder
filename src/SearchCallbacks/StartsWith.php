<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\SearchCallbacks;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use PowerVending\LaravelApiQueryBuilder\CategorizedValues;

class StartsWith extends AbstractCallback
{
    public static function operator(): string
    {
        return 'STARTS_WITH:';
    }

    public static function supportsComparableTypes(): bool
    {
        return false;
    }

    /**
     * @param  Builder  $builder
     * @param  string  $column
     * @param  CategorizedValues  $values
     * @return void
     *
     * @throws Exception
     */
    public function execute(Builder $builder, string $column, CategorizedValues $values): void
    {
        $this->startsWithCallback($builder, $column, $values, 'starts_with');
    }
}

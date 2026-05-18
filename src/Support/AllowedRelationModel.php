<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Support;

use Illuminate\Database\Eloquent\Model;

class AllowedRelationModel
{
    public static function isAllowed(Model $model): bool
    {
        return str_starts_with(get_class($model), 'App\\');
    }
}
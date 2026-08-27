<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Self-referencing model, used to assert that a table joined against itself gets an alias.
 */
class CategoryModel extends Model
{
    protected $table = 'categories';

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }
}

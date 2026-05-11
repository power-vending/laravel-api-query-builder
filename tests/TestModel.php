<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model {}

class NestedModel extends Model
{
    protected $table = 'nested';
}

class RelatedModel extends Model
{
    protected $table = 'related';

    public function nested()
    {
        return $this->belongsTo(NestedModel::class, 'nested_id', 'id');
    }
}

class TestModel extends Model
{
    protected $table = 'test';

    public function tags()
    {
        return $this->morphToMany(Tag::class, "taggable");
    }

    public function related()
    {
        return $this->belongsTo(RelatedModel::class, 'related_id', 'id');
    }
}

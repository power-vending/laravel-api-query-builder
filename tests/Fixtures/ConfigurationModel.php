<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use PowerVending\LaravelApiQueryBuilder\Traits\ApiQueryBuilder;

class ConfigurationModel extends Model
{
    use ApiQueryBuilder;

    protected $table = 'configurations';

    public $timestamps = false;

    protected $guarded = [];
}

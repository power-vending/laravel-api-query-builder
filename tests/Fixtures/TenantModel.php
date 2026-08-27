<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use PowerVending\LaravelApiQueryBuilder\Traits\ApiQueryBuilder;

class TenantModel extends Model
{
    use ApiQueryBuilder;

    protected $table = 'tenants';

    public $timestamps = false;

    protected $guarded = [];
}

<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use PowerVending\LaravelApiQueryBuilder\Traits\ApiQueryBuilder;

/**
 * Base table and both related tables carry an 'id' and a 'name', which is what
 * makes a bare where('id', ...) ambiguous once order_by adds the join.
 */
class TerminalModel extends Model
{
    use ApiQueryBuilder;

    protected $table = 'terminals';

    public $timestamps = false;

    protected $guarded = [];

    public function company()
    {
        return $this->belongsTo(CompanyFixtureModel::class, 'company_id', 'id');
    }

    public function tenant()
    {
        return $this->belongsTo(TenantModel::class, 'tenant_id', 'id');
    }
}

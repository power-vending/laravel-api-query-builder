<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use PowerVending\LaravelApiQueryBuilder\Traits\ApiQueryBuilder;

class CompanyFixtureModel extends Model
{
    use ApiQueryBuilder;

    protected $table = 'companies';

    public $timestamps = false;

    protected $guarded = [];

    public function configurations()
    {
        return $this->belongsToMany(
            ConfigurationModel::class,
            'companies_has_configurations',
            'company_id',
            'configuration_id'
        );
    }
}

<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Model with two relations pointing to the same table, a many-to-many and a polymorphic relation.
 */
class TicketModel extends Model
{
    protected $table = 'tickets';

    public function createdBy()
    {
        return $this->belongsTo(AuthorModel::class, 'created_by', 'id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(AuthorModel::class, 'updated_by', 'id');
    }

    public function partners()
    {
        return $this->belongsToMany(PartnerModel::class, 'partner_ticket', 'ticket_id', 'partner_id');
    }

    public function comments()
    {
        return $this->morphMany(CommentModel::class, 'commentable');
    }
}

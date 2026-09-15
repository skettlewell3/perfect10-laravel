<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NationalTeam extends Model
{
    protected $table = 'teams_national';

    protected $primaryKey = 'team_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public function team(): BelongsTo
    {
        return $this->belongsTo(
            Team::class,
            'team_id',
            'team_id'
        );
    }

    public function fa(): BelongsTo
    {
        return $this->belongsTo(
            FootballAssociation::class,
            'fa_id',
            'fa_id'
        );
    }
}
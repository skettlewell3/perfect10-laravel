<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $table = 'campaigns';

    protected $primaryKey = 'campaign_id';

    public $timestamps = false;

    protected $guarded = [];

    public function fixtures(): HasMany
    {
        return $this->hasMany(
            Fixture::class,
            'campaign_id',
            'campaign_id'
        );
    }

    public function stages(): HasMany
    {
        return $this->hasMany(
            Stage::class,
            'campaign_id',
            'campaign_id'
        );
    }

    public function gameweeks(): HasMany
    {
        return $this->hasMany(
            Gameweek::class,
            'campaign_id',
            'campaign_id'
        );
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(
            Competition::class,
            'competition_id',
            'competition_id'
        );
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}

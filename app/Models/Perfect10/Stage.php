<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Stage extends Model
{
    protected $table = 'stages';

    protected $primaryKey = 'stage_id';

    public $timestamps = false;

    protected $guarded = [];

    public function fixtures(): HasMany
    {
        return $this->hasMany(
            Fixture::class,
            'stage_id',
            'stage_id'
        );
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(
            Campaign::class,
            'campaign_id',
            'campaign_id'
        );
    }

    public function gameweek(): HasOne
    {
        return $this->hasOne(
            Gameweek::class,
            'stage_id',
            'stage_id'
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

    public function ties(): HasMany
    {
        return $this->hasMany(
            Tie::class,
            'stage_id',
            'stage_id'
        );
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_finished' => 'boolean',
            'is_live' => 'boolean',
            'order_index' => 'integer',
            'first_fixture_id' => 'integer',
            'last_fixture_id' => 'integer',
        ];
    }
}

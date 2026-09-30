<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tie extends Model
{
    protected $table = 'ties';

    protected $primaryKey = 'tie_id';

    const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Stage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(
            Stage::class,
            'stage_id',
            'stage_id'
        );
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function teamA(): BelongsTo
    {
        return $this->belongsTo(
            Team::class,
            'team_a_id',
            'team_id'
        );
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function teamB(): BelongsTo
    {
        return $this->belongsTo(
            Team::class,
            'team_b_id',
            'team_id'
        );
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(
            Team::class,
            'winner_team_id',
            'team_id'
        );
    }

    /**
     * @return HasMany<Fixture, $this>
     */
    public function fixtures(): HasMany
    {
        return $this->hasMany(
            Fixture::class,
            'tie_id',
            'tie_id'
        );
    }
}

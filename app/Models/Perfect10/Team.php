<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Team extends Model
{
    protected $table = 'teams';

    protected $primaryKey = 'team_id';

    protected $guarded = [];

    /**
     * @return HasMany<Fixture, $this>
     */
    public function homeFixtures(): HasMany
    {
        return $this->hasMany(
            Fixture::class,
            'home_team_id',
            'team_id'
        );
    }

    /**
     * @return HasMany<Fixture, $this>
     */
    public function awayFixtures(): HasMany
    {
        return $this->hasMany(
            Fixture::class,
            'away_team_id',
            'team_id'
        );
    }

    /**
     * @return HasOne<DomesticTeam, $this>
     */
    public function domesticProfile(): HasOne
    {
        return $this->hasOne(
            DomesticTeam::class,
            'team_id',
            'team_id'
        );
    }

    /**
     * @return HasOne<NationalTeam, $this>
     */
    public function nationalProfile(): HasOne
    {
        return $this->hasOne(
            NationalTeam::class,
            'team_id',
            'team_id'
        );
    }
}

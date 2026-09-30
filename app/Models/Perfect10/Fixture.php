<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $fixture_id
 * @property int $campaign_id
 * @property int $stage_id
 * @property int $venue_id
 * @property int|null $home_team_id
 * @property int|null $away_team_id
 * @property Carbon $kickoff_at
 */
class Fixture extends Model
{
    protected $table = 'fixtures';

    protected $primaryKey = 'fixture_id';

    protected $keyType = 'int';

    public $incrementing = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kickoff_at' => 'datetime',
            'prediction_open_at' => 'datetime',
            'prediction_close_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'leg_number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(
            Team::class,
            'home_team_id',
            'team_id'
        );
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(
            Team::class,
            'away_team_id',
            'team_id'
        );
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
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(
            Campaign::class,
            'campaign_id',
            'campaign_id'
        );
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(
            Venue::class,
            'venue_id',
            'venue_id'
        );
    }

    /**
     * @return HasOne<Result, $this>
     */
    public function result(): HasOne
    {
        return $this->hasOne(
            Result::class,
            'fixture_id',
            'fixture_id'
        );
    }

    /**
     * @return BelongsTo<Tie, $this>
     */
    public function tie(): BelongsTo
    {
        return $this->belongsTo(
            Tie::class,
            'tie_id',
            'tie_id'
        );
    }

    /**
     * @return HasMany<Prediction, $this>
     */
    public function predictions(): HasMany
    {
        return $this->hasMany(
            Prediction::class,
            'fixture_id',
            'fixture_id'
        );
    }

    /**
     * @return HasMany<PredictionScore, $this>
     */
    public function predictionScores(): HasMany
    {
        return $this->hasMany(
            PredictionScore::class,
            'fixture_id',
            'fixture_id'
        );
    }

    public function previousMeeting(): ?self
    {
        return self::query()
            ->with('result')
            ->where(function ($query) {
                $query
                    ->where(function ($query) {
                        $query
                            ->where('home_team_id', $this->home_team_id)
                            ->where('away_team_id', $this->away_team_id);
                    })
                    ->orWhere(function ($query) {
                        $query
                            ->where('home_team_id', $this->away_team_id)
                            ->where('away_team_id', $this->home_team_id);
                    });
            })
            ->where('fixture_id', '!=', $this->fixture_id)
            ->where('kickoff_at', '<', $this->kickoff_at)
            ->orderByDesc('kickoff_at')
            ->first();
    }
}

<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionScore extends Model
{
    protected $table = 'prediction_scores';

    protected $primaryKey = 'prediction_id';

    protected $keyType = 'int';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'correct_result' => 'boolean',
            'correct_home_goals' => 'boolean',
            'correct_away_goals' => 'boolean',
            'correct_goal_difference' => 'boolean',
            'correct_total_goals' => 'boolean',
            'points_total' => 'integer',
            'perfect_10' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Prediction, $this>
     */
    public function prediction(): BelongsTo
    {
        return $this->belongsTo(
            Prediction::class,
            'prediction_id',
            'prediction_id'
        );
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(
            Profile::class,
            'profile_id',
            'profile_id'
        );
    }

    /**
     * @return BelongsTo<Fixture, $this>
     */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(
            Fixture::class,
            'fixture_id',
            'fixture_id'
        );
    }
}

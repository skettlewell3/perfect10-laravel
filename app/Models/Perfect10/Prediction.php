<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Prediction extends Model
{
    protected $table = 'predictions';

    protected $primaryKey = 'prediction_id';

    protected $keyType = 'int';

    public $incrementing = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'pred_home_goals' => 'integer',
            'pred_away_goals' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(
            Profile::class,
            'profile_id',
            'profile_id'
        );
    }

    public function fixture(): BelongsTo
    {
        return $this->belongsTo(
            Fixture::class,
            'fixture_id',
            'fixture_id'
        );
    }

    public function score(): HasOne
    {
        return $this->hasOne(
            PredictionScore::class,
            'prediction_id',
            'prediction_id'
        );
    }
}

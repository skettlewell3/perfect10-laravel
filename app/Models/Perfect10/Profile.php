<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Profile extends Model
{
    protected $table = 'profiles';

    protected $primaryKey = 'profile_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(
            Prediction::class,
            'profile_id',
            'profile_id'
        );
    }

    public function userProfile(): HasOne
    {
        return $this->hasOne(
            UserProfile::class,
            'profile_id',
            'profile_id'
        );
    }

    public function clubProfile(): HasOne
    {
        return $this->hasOne(
            ClubProfile::class,
            'profile_id',
            'profile_id'
        );
    }

    public function botProfile(): HasOne
    {
        return $this->hasOne(
            BotProfile::class,
            'profile_id',
            'profile_id'
        );
    }

    public function predictionScores(): HasMany
    {
        return $this->hasMany(
            PredictionScore::class,
            'profile_id',
            'profile_id'
        );
    }
}

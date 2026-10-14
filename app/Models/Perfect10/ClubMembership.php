<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $club_id
 * @property string $profile_id
 * @property string $club_role
 * @property Carbon $created_at
 */
class ClubMembership extends Model
{
    protected $table = 'club_memberships';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ClubProfile, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(
            ClubProfile::class,
            'club_id',
            'profile_id'
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
}

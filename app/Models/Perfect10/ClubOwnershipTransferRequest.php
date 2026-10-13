<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubOwnershipTransferRequest extends Model
{
    protected $table = 'club_ownership_transfer_requests';

    protected $primaryKey = 'request_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
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
     * @return BelongsTo<UserProfile, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(
            UserProfile::class,
            'owner_profile_id',
            'profile_id'
        );
    }

    /**
     * @return BelongsTo<UserProfile, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(
            UserProfile::class,
            'target_profile_id',
            'profile_id'
        );
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function ownerProfile(): BelongsTo
    {
        return $this->belongsTo(
            Profile::class,
            'owner_profile_id',
            'profile_id'
        );
    }
}

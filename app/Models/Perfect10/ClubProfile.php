<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClubProfile extends Model
{
    protected $table = 'club_profiles';

    protected $primaryKey = 'profile_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'invite_only' => 'boolean',
            'is_active' => 'boolean',
            'is_deleted' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'club_code';
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
     * @return BelongsTo<UserProfile, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            UserProfile::class,
            'created_by',
            'profile_id'
        );
    }

    /**
     * @return HasMany<ClubMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(
            ClubMembership::class,
            'club_id',
            'profile_id'
        );
    }

    /**
     * @return HasMany<ClubInvite, $this>
     */
    public function invites(): HasMany
    {
        return $this->hasMany(
            ClubInvite::class,
            'club_id',
            'profile_id'
        );
    }

    /**
     * @return HasMany<ClubOwnershipTransferRequest, $this>
     */
    public function ownershipTransferRequests(): HasMany
    {
        return $this->hasMany(
            ClubOwnershipTransferRequest::class,
            'club_id',
            'profile_id'
        );
    }
}
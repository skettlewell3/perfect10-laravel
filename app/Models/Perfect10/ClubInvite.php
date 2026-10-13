<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubInvite extends Model
{
    protected $table = 'club_invites';

    protected $primaryKey = 'invite_id';

    protected $keyType = 'string';

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
    public function sender(): BelongsTo
    {
        return $this->belongsTo(
            Profile::class,
            'sender_profile_id',
            'profile_id'
        );
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(
            Profile::class,
            'recipient_profile_id',
            'profile_id'
        );
    }
}

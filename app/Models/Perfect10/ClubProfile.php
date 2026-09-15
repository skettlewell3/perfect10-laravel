<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function profile(): BelongsTo
    {
        return $this->belongsTo(
            Profile::class,
            'profile_id',
            'profile_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            UserProfile::class,
            'created_by',
            'profile_id'
        );
    }
}
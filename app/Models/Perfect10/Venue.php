<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Venue extends Model
{
    protected $table = 'venues';

    protected $primaryKey = 'venue_id';

    public $timestamps = false;

    protected $guarded = [];

    public function fixtures(): HasMany
    {
        return $this->hasMany(
            Fixture::class,
            'venue_id',
            'venue_id'
        );
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(
            City::class,
            'city_id',
            'city_id'
        );
    }
}
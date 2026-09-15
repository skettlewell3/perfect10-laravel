<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $table = 'cities';

    protected $primaryKey = 'city_id';

    public $timestamps = false;

    protected $guarded = [];

    public function country(): BelongsTo
    {
        return $this->belongsTo(
            Country::class,
            'country_id',
            'country_id'
        );
    }

    public function venues(): HasMany
    {
        return $this->hasMany(
            Venue::class,
            'city_id',
            'city_id'
        );
    }
}
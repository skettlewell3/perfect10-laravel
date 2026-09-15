<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $table = 'countries';

    protected $primaryKey = 'country_id';

    public $timestamps = false;

    protected $guarded = [];

    public function cities(): HasMany
    {
        return $this->hasMany(
            City::class,
            'country_id',
            'country_id'
        );
    }

    public function footballAssociations(): HasMany
    {
        return $this->hasMany(
            FootballAssociation::class,
            'country_id',
            'country_id'
        );
    }
}

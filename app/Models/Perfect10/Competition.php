<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model
{
    protected $table = 'competitions';

    protected $primaryKey = 'competition_id';

    public $timestamps = false;

    protected $guarded = [];

    public function flavourLink(): BelongsToMany
    {
        return $this->belongsToMany(
            Flavour::class,
            'flavour_competitions',
            'competition_id',
            'flavour_id'
        );
    }

    public function flavour(): ?Flavour
    {
        return $this->flavourLink()->first();
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(
            Campaign::class,
            'competition_id',
            'competition_id'
        );
    }

    public function stages(): HasMany
    {
        return $this->hasMany(
            Stage::class,
            'competition_id',
            'competition_id'
        );
    }
}

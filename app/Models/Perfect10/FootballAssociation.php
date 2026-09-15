<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FootballAssociation extends Model
{
    protected $table = 'fa';

    protected $primaryKey = 'fa_id';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fifa_member' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(
            Country::class,
            'country_id',
            'country_id'
        );
    }

    public function confederation(): BelongsTo
    {
        return $this->belongsTo(
            Confederation::class,
            'confederation_id',
            'confederation_id'
        );
    }

    public function domesticTeams(): HasMany
    {
        return $this->hasMany(
            DomesticTeam::class,
            'fa_id',
            'fa_id'
        );
    }

    public function nationalTeams(): HasMany
    {
        return $this->hasMany(
            NationalTeam::class,
            'fa_id',
            'fa_id'
        );
    }
}

<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Flavour extends Model
{
    protected $table = 'flavours';

    protected $primaryKey = 'flavour_id';

    const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(
            PredictionFormat::class,
            'format_id',
            'format_id'
        );
    }

    public function competitionLink(): BelongsToMany
    {
        return $this->belongsToMany(
            Competition::class,
            'flavour_competitions',
            'flavour_id',
            'competition_id'
        );
    }

    public function competition(): ?Competition
    {
        return $this->competitionLink()->first();
    }
}
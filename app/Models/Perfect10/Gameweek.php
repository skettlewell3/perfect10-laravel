<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gameweek extends Model
{
    protected $table = 'gameweeks';

    protected $primaryKey = 'gameweek_id';

    protected $keyType = 'int';

    public $incrementing = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'campaign_id' => 'integer',
            'stage_id' => 'integer',
            'gameweek_number' => 'integer',
            'prediction_open_at' => 'datetime',
            'prediction_close_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(
            Stage::class,
            'stage_id',
            'stage_id'
        );
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(
            Campaign::class,
            'campaign_id',
            'campaign_id'
        );
    }
}
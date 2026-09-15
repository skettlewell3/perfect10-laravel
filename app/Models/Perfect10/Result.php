<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    protected $table = 'results';

    protected $primaryKey = 'fixture_id';

    protected $keyType = 'int';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ft_home_goals' => 'integer',
            'ft_away_goals' => 'integer',
            'final_home_goals' => 'integer',
            'final_away_goals' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function fixture(): BelongsTo
    {
        return $this->belongsTo(
            Fixture::class,
            'fixture_id',
            'fixture_id'
        );
    }
}
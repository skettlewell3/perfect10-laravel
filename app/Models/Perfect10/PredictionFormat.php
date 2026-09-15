<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PredictionFormat extends Model
{
    protected $table = 'prediction_formats';

    protected $primaryKey = 'format_id';

    public $timestamps = false;

    protected $guarded = [];

    public function flavours(): HasMany
    {
        return $this->hasMany(
            Flavour::class,
            'format_id',
            'format_id'
        );
    }
}

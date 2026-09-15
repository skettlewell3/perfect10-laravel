<?php

namespace App\Models\Perfect10;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Confederation extends Model
{
    protected $table = 'confederations';

    protected $primaryKey = 'confederation_id';

    public $timestamps = false;

    protected $guarded = [];

    public function footballAssociations(): HasMany
    {
        return $this->hasMany(
            FootballAssociation::class,
            'confederation_id',
            'confederation_id'
        );
    }
}

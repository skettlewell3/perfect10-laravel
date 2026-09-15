<?php

namespace App\Services\Perfect10;

use App\Models\Perfect10\Competition;
use App\Models\Perfect10\Flavour;
use RuntimeException;

class FlavourContextService
{
    public function competitionFor(Flavour $flavour): Competition
    {
        $competitions = $flavour
            ->competitionLink()
            ->get();

        if ($competitions->count() !== 1) {
            throw new RuntimeException(
                "Flavour {$flavour->flavour_id} must resolve to exactly one competition."
            );
        }

        return $competitions->first();
    }
}
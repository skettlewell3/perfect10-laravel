<?php

namespace App\Services\Perfect10;

use App\Models\Perfect10\Campaign;
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

    public function activeCampaignFor(Flavour $flavour): Campaign
    {
        $competition = $this->competitionFor($flavour);

        $campaigns = $competition
            ->campaigns()
            ->where('is_active', true)
            ->get();

        if ($campaigns->count() !== 1) {
            throw new RuntimeException(
                "Competition {$competition->competition_id} must resolve to exactly one active campaign."
            );
        }

        return $campaigns->first();
    }
}

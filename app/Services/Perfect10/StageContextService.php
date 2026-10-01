<?php

namespace App\Services\Perfect10;

use App\Models\Perfect10\Campaign;
use App\Models\Perfect10\Competition;
use App\Models\Perfect10\Stage;
use Illuminate\Database\Eloquent\Collection;

class StageContextService
{
    /**
     * All stages for a competition, including historical campaigns.
     *
     * @return Collection<int, Stage>
     */
    public function stagesForCompetition(
        Competition $competition
    ): Collection {
        return $competition
            ->stages()
            ->orderBy('order_index')
            ->get();
    }

    /**
     * Stages belonging to the selected campaign.
     *
     * @return Collection<int, Stage>
     */
    public function stagesForCampaign(
        Campaign $campaign
    ): Collection {
        return Stage::query()
            ->where('campaign_id', $campaign->getKey())
            ->where(
                'competition_id',
                $campaign->getAttribute('competition_id')
            )
            ->orderBy('order_index')
            ->get();
    }

    /**
     * Find the active stage.
     *
     * @param  Collection<int, Stage>  $stages
     */
    public function activeStage(
        Collection $stages
    ): ?Stage {
        return $stages->first(
            fn (Stage $stage): bool => (bool) $stage->getAttribute('is_active')
        );
    }

    /**
     * Select the stage displayed in the profile ticker.
     *
     * @param  Collection<int, Stage>  $stages
     */
    public function tickerStage(
        Collection $stages
    ): ?Stage {
        $live = $stages->first(
            fn (Stage $stage): bool => (bool) $stage->getAttribute('is_live')
        );

        if ($live !== null) {
            return $live;
        }

        $finished = $stages
            ->filter(
                fn (Stage $stage): bool => (bool) $stage->getAttribute('is_finished')
            )
            ->sortByDesc('order_index')
            ->first();

        if ($finished !== null) {
            return $finished;
        }

        return $this->activeStage($stages);
    }
}

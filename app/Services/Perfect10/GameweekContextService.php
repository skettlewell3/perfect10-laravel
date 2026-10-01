<?php

namespace App\Services\Perfect10;

use App\Models\Perfect10\Campaign;
use App\Models\Perfect10\Gameweek;
use App\Models\Perfect10\Stage;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class GameweekContextService
{
    /**
     * Retrieve gameweeks for the selected campaign.
     *
     * @return Collection<int, Gameweek>
     */
    public function gameweeksForCampaign(
        Campaign $campaign
    ): Collection {
        return $campaign
            ->gameweeks()
            ->orderBy('gameweek_number')
            ->get();
    }

    /**
     * @param  Collection<int, Gameweek>  $gameweeks
     */
    public function activeGameweek(
        Collection $gameweeks
    ): ?Gameweek {
        return $gameweeks->first(
            fn (Gameweek $gameweek): bool => $gameweek->getAttribute('status') === 'active'
        );
    }

    /**
     * Return the gameweek immediately following the active one.
     *
     * @param  Collection<int, Gameweek>  $gameweeks
     */
    public function nextGameweek(
        Collection $gameweeks
    ): ?Gameweek {
        $ordered = $gameweeks
            ->sortBy('gameweek_number')
            ->values();

        $active = $this->activeGameweek($ordered);

        if ($active === null) {
            return null;
        }

        $position = $ordered->search(
            fn (Gameweek $gameweek): bool => $gameweek->getKey() === $active->getKey()
        );

        if ($position === false) {
            return null;
        }

        return $ordered->get($position + 1);
    }

    /**
     * Verify that the active stage and gameweek agree.
     *
     * Call this only for GWK prediction formats.
     *
     * @param  Collection<int, Gameweek>  $gameweeks
     */
    public function validatedActiveGameweek(
        Collection $gameweeks,
        ?Stage $activeStage
    ): ?Gameweek {
        $activeGameweek = $this->activeGameweek($gameweeks);

        if ($activeStage === null && $activeGameweek === null) {
            return null;
        }

        if (
            $activeStage === null ||
            $activeGameweek === null ||
            (int) $activeGameweek->getAttribute('stage_id') !==
                (int) $activeStage->getKey()
        ) {
            throw new RuntimeException(
                'The active stage and active gameweek must correspond.'
            );
        }

        return $activeGameweek;
    }
}

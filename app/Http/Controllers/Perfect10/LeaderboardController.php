<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Models\Perfect10\Flavour;
use App\Services\Perfect10\FlavourContextService;
use App\Services\Perfect10\GameweekContextService;
use App\Services\Perfect10\LeaderboardContextService;
use App\Services\Perfect10\StageContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public function __construct(
        private FlavourContextService $flavourContext,
        private StageContextService $stageContext,
        private GameweekContextService $gameweekContext,
        private LeaderboardContextService $leaderboardContext,
    ) {}

    public function index(Request $request): Response
    {
        $flavours = $this->flavourContext->availableFlavours();

        abort_if(
            $flavours->isEmpty(),
            404,
            'No active Perfect10 flavour is available.'
        );

        $selectedFlavourData = $this->flavourContext
            ->selectFlavour(
                $flavours,
                $request->string('flavour')->toString()
            );

        $flavourId = (int) $selectedFlavourData['flavour_id'];

        $flavour = Flavour::query()
            ->findOrFail($flavourId);

        $competition = $this->flavourContext
            ->competitionFor($flavour);

        $campaign = $this->flavourContext
            ->activeCampaignFor($flavour);

        $stages = $this->stageContext
            ->stagesForCampaign($campaign);

        $tickerStage = $this->stageContext
            ->tickerStage($stages);

        /*
         * Start with the universal stage rules used by both formats.
         *
         * This preserves the existing behaviour where leaderboard
         * rounds become selectable once live or finished.
         */

        $leaderboardStages = $stages
            ->filter(
                fn ($stage): bool => (bool) data_get($stage, 'is_live')
                    || (bool) data_get($stage, 'is_finished')
            )
            ->values();

        /*
         * GWK still uses stages for leaderboard scope.
         *
         * The additional rule is simply that a selectable stage must
         * also represent a gameweek. This excludes postponed and
         * rearranged bonus stages naturally.
         */

        if ($selectedFlavourData['format_code'] === 'GWK') {
            $gameweeks = $this->gameweekContext
                ->gameweeksForCampaign($campaign);

            $gameweekStageIds = $gameweeks
                ->pluck('stage_id')
                ->map(fn ($stageId): int => (int) $stageId);

            $leaderboardStages = $leaderboardStages
                ->filter(
                    fn ($stage): bool => $gameweekStageIds->contains(
                        (int) data_get($stage, 'stage_id')
                    )
                )
                ->values();
        }

        /*
         * Filters.
         */

        $scopeType = $request->string('scope')->toString();

        if (! in_array($scopeType, ['campaign', 'stage'], true)) {
            $scopeType = 'stage';
        }

        $population = $request->string('population')->toString();

        if (! in_array(
            $population,
            ['global', 'users', 'clubs', 'bots'],
            true
        )) {
            $population = 'global';
        }

        /*
         * Resolve the requested/default stage.
         */

        $requestedStageId = $request->filled('stage')
            ? $request->integer('stage')
            : null;

        $selectedStageId = null;

        if ($requestedStageId !== null) {
            $requestedStageIsValid = $leaderboardStages
                ->contains(
                    fn ($stage): bool => (int) data_get($stage, 'stage_id')
                        === $requestedStageId
                );

            if ($requestedStageIsValid) {
                $selectedStageId = $requestedStageId;
            }
        }

        /*
         * Prefer the ticker stage when it is a valid leaderboard stage.
         */

        if ($selectedStageId === null && $tickerStage !== null) {
            $tickerStageId = (int) data_get(
                $tickerStage,
                'stage_id'
            );

            $tickerStageIsValid = $leaderboardStages
                ->contains(
                    fn ($stage): bool => (int) data_get($stage, 'stage_id')
                        === $tickerStageId
                );

            if ($tickerStageIsValid) {
                $selectedStageId = $tickerStageId;
            }
        }

        /*
         * Otherwise use the latest selectable stage.
         */

        if ($selectedStageId === null) {
            $lastStage = $leaderboardStages->last();

            if ($lastStage !== null) {
                $selectedStageId = (int) data_get(
                    $lastStage,
                    'stage_id'
                );
            }
        }

        $scopeId = $scopeType === 'campaign'
            ? (int) data_get($campaign, 'campaign_id')
            : $selectedStageId;

        $leaderboard = $this->leaderboardContext
            ->leaderboard(
                $flavourId,
                $scopeType,
                $scopeId,
                $population
            );

        return Inertia::render('perfect10/leaderboards', [
            'flavours' => $flavours,
            'flavour' => $selectedFlavourData,
            'competition' => $competition,
            'campaign' => $campaign,

            'leaderboard' => $leaderboard,

            'leaderboardStages' => $leaderboardStages,

            'filters' => [
                'scopeType' => $scopeType,
                'scopeId' => $scopeId,
                'population' => $population,
            ],
        ]);
    }
}

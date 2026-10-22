<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\FixtureContextService;
use App\Services\Perfect10\GameweekContextService;
use App\Services\Perfect10\LeaderboardContextService;
use App\Services\Perfect10\Perfect10RequestContext;
use App\Services\Perfect10\PredictionContextService;
use App\Services\Perfect10\StageContextService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private Perfect10RequestContext $context,
        private FixtureContextService $fixtureContext,
        private StageContextService $stageContext,
        private GameweekContextService $gameweekContext,
        private PredictionContextService $predictionContext,
        private CurrentProfileService $currentProfile,
        private LeaderboardContextService $leaderboardContext,
    ) {}

    public function index(): Response
    {
        $flavours = $this->context->flavours();

        $selectedFlavourData = $this->context
            ->selectedFlavourData();

        $flavour = $this->context->flavour();

        $competition = $this->context->competition();

        $campaign = $this->context->campaign();

        $stages = $this->context->stages();

        $fixtures = $this->fixtureContext
            ->fixturesForFlavour($flavour);

        /*
         * Universal stage context.
         */

        $activeStage = $this->stageContext
            ->activeStage($stages);

        $tickerStage = $this->stageContext
            ->tickerStage($stages);

        /*
         * Current Perfect10 profile.
         */

        $profileId = $this->currentProfile->profileId();

        /*
         * Gameweek context.
         */

        $gameweeks = null;
        $activeGameweek = null;
        $nextGameweek = null;

        /*
         * Dashboard snapshot data.
         */

        $predictionsMap = [];
        $gameweekReview = null;

        if ($selectedFlavourData['format_code'] === 'GWK') {
            $gameweeks = $this->gameweekContext
                ->gameweeksForCampaign($campaign);

            $activeGameweek = $this->gameweekContext
                ->validatedActiveGameweek(
                    $gameweeks,
                    $activeStage
                );

            $nextGameweek = $this->gameweekContext
                ->nextGameweek($gameweeks);

            /*
             * Saved predictions.
             *
             * The React dashboard checks these against the fixtures
             * belonging to the active gameweek.
             */

            if ($profileId !== null && $activeGameweek !== null) {
                $predictionsMap = $this->predictionContext
                    ->predictionsForFlavour(
                        $flavour,
                        $profileId
                    );
            }

            /*
             * Gameweek review.
             *
             * Use the same ticker stage and leaderboard service
             * already used elsewhere in Perfect10.
             */

            if ($profileId !== null && $tickerStage !== null) {
                $stageId = (int) data_get(
                    $tickerStage,
                    'stage_id'
                );

                $leaderboard = $this->leaderboardContext
                    ->leaderboard(
                        (int) $selectedFlavourData['flavour_id'],
                        'stage',
                        $stageId,
                        'global'
                    );

                $playerRow = $leaderboard->first(
                    fn (array $row): bool => (string) data_get($row, 'profile_id') ===
                        $profileId
                );

                if ($playerRow !== null) {
                    $gameweekReview = [
                        'stage_code' => (string) data_get(
                            $tickerStage,
                            'stage_code',
                            ''
                        ),

                        'rank' => data_get($playerRow, 'rank') !== null
                            ? (int) data_get($playerRow, 'rank')
                            : null,

                        'points_total' => (int) data_get(
                            $playerRow,
                            'points_total',
                            0
                        ),

                        'perfect_10s' => (int) data_get(
                            $playerRow,
                            'perfect_10s',
                            0
                        ),

                        'correct_results' => (int) (
                            data_get($playerRow, 'correct_results')
                            ?? data_get($playerRow, 'correct_result', 0)
                        ),

                        'correct_home_goals' => (int) data_get(
                            $playerRow,
                            'correct_home_goals',
                            0
                        ),

                        'correct_away_goals' => (int) data_get(
                            $playerRow,
                            'correct_away_goals',
                            0
                        ),

                        'correct_goal_difference' => (int) data_get(
                            $playerRow,
                            'correct_goal_difference',
                            0
                        ),

                        'correct_total_goals' => (int) data_get(
                            $playerRow,
                            'correct_total_goals',
                            0
                        ),
                    ];
                }
            }
        }

        return Inertia::render('perfect10/dashboard', [
            'flavours' => $flavours,
            'flavour' => $selectedFlavourData,
            'competition' => $competition,
            'campaign' => $campaign,
            'fixtures' => $fixtures,

            'stageContext' => [
                'stages' => $stages,
                'activeStage' => $activeStage,
                'tickerStage' => $tickerStage,
            ],

            'gameweekContext' => $gameweeks === null
                ? null
                : [
                    'gameweeks' => $gameweeks,
                    'activeGameweek' => $activeGameweek,
                    'nextGameweek' => $nextGameweek,
                ],

            'predictionsMap' => $predictionsMap,
            'gameweekReview' => $gameweekReview,
        ]);
    }
}

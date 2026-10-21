<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Models\Perfect10\Team;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\FixtureContextService;
use App\Services\Perfect10\GameweekContextService;
use App\Services\Perfect10\Perfect10RequestContext;
use App\Services\Perfect10\PredictionContextService;
use Inertia\Inertia;
use Inertia\Response;

class GameweekController extends Controller
{
    public function __construct(
        private Perfect10RequestContext $context,
        private GameweekContextService $gameweekContext,
        private FixtureContextService $fixtureContext,
        private PredictionContextService $predictionContext,
        private CurrentProfileService $currentProfile,
    ) {}

    public function index(): Response
    {
        $flavours = $this->context->flavours();

        $selected = $this->context->selectedFlavourData();

        abort_unless(
            $selected['format_code'] === 'GWK',
            404,
            'The Gameweek page is only available for GWK formats.'
        );

        $flavour = $this->context->flavour();

        $campaign = $this->context->campaign();

        // Resolve the active gameweek.
        $gameweeks = $this->gameweekContext
            ->gameweeksForCampaign($campaign);

        $activeGameweek = $this->gameweekContext
            ->activeGameweek($gameweeks);

        // Retrieve only fixtures belonging to the active gameweek.
        $fixtures = $activeGameweek
            ? collect(
                $this->fixtureContext->fixturesForFlavour($flavour)
            )
                ->filter(
                    fn (array $fixture): bool => (int) $fixture['gameweek_id'] ===
                        (int) $activeGameweek->getKey()
                )
                ->sortBy('kickoff_at')
                ->values()
                ->all()
            : [];

        // Resolve the current user's saved predictions.
        $allPredictions = $this->predictionContext
            ->predictionsForFlavour(
                $flavour,
                $this->currentProfile->profileId()
            );

        $fixtureIds = collect($fixtures)
            ->pluck('fixture_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $predictionsMap = collect($allPredictions)
            ->only($fixtureIds)
            ->all();

        // Retrieve the relevant teams and their presentation data.
        $teamIds = collect($fixtures)
            ->flatMap(fn (array $fixture) => [
                $fixture['home_team_id'],
                $fixture['away_team_id'],
            ])
            ->filter(fn ($id) => $id !== null)
            ->unique()
            ->values();

        $teamsMap = Team::query()
            ->whereIn('team_id', $teamIds)
            ->with([
                'nationalProfile:team_id,flag_code',
            ])
            ->get([
                'team_id',
                'team_name',
                'color_1',
                'color_2',
                'color_3',
            ])
            ->mapWithKeys(function (Team $team): array {
                return [
                    $team->team_id => [
                        'team_id' => $team->team_id,
                        'team_name' => $team->team_name,
                        'flag_code' => $team->nationalProfile?->flag_code,
                        'color_1' => $team->color_1,
                        'color_2' => $team->color_2,
                        'color_3' => $team->color_3,
                    ],
                ];
            });

        return Inertia::render('perfect10/gameweek', [
            'flavours' => $flavours,
            'flavour' => $selected,

            'activeGameweek' => $activeGameweek
                ? [
                    'gameweek_id' => $activeGameweek->gameweek_id,
                    'gameweek_number' => $activeGameweek->gameweek_number,
                    'status' => $activeGameweek->status,
                    'prediction_open_at' => $activeGameweek->prediction_open_at,
                    'prediction_close_at' => $activeGameweek->prediction_close_at,
                ]
                : null,

            'fixtures' => $fixtures,
            'teamsMap' => $teamsMap,
            'predictionsMap' => $predictionsMap,
        ]);
    }
}

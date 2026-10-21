<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Models\Perfect10\Team;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\FixtureContextService;
use App\Services\Perfect10\Perfect10RequestContext;
use App\Services\Perfect10\PredictionContextService;
use Inertia\Inertia;
use Inertia\Response;

class FixturesController extends Controller
{
    public function __construct(
        private Perfect10RequestContext $context,
        private FixtureContextService $fixtureContext,
        private PredictionContextService $predictionContext,
        private CurrentProfileService $currentProfile,
    ) {}

    public function index(): Response
    {
        $flavours = $this->context->flavours();

        $selected = $this->context->selectedFlavourData();

        $flavour = $this->context->flavour();

        $fixtures = $this->fixtureContext
            ->fixturesForFlavour($flavour);

        $predictionsMap = $this->predictionContext->predictionsForFlavour(
            $flavour,
            $this->currentProfile->profileId()
        );

        // Collect unique team IDs referenced by the fixtures.
        $teamIds = collect($fixtures)
            ->flatMap(fn (array $fixture) => [
                $fixture['home_team_id'],
                $fixture['away_team_id'],
            ])
            ->filter(fn ($id) => $id !== null)
            ->unique()
            ->values();

        // Retrieve the required presentation attributes.
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

        return Inertia::render('perfect10/fixtures', [
            'flavours' => $flavours,
            'flavour' => $selected,
            'fixtures' => $fixtures,
            'teamsMap' => $teamsMap,
            'predictionsMap' => $predictionsMap,
        ]);
    }
}

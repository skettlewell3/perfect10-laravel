<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Models\Perfect10\Flavour;
use App\Models\Perfect10\Team;
use App\Services\Perfect10\FixtureContextService;
use App\Services\Perfect10\FlavourContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FixturesController extends Controller
{
    public function __construct(
        private FlavourContextService $flavourContext,
        private FixtureContextService $fixtureContext,
    ) {}

    public function index(Request $request): Response
    {
        $flavours = $this->flavourContext->availableFlavours();

        abort_if(
            $flavours->isEmpty(),
            404,
            'No active Perfect10 flavour is available.'
        );

        $selected = $this->flavourContext->selectFlavour(
            $flavours,
            (string) $request->route('flavour')
        );

        $flavour = Flavour::query()
            ->findOrFail($selected['flavour_id']);

        $fixtures = $this->fixtureContext
            ->fixturesForFlavour($flavour);

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
        ]);
    }
}

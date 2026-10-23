<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\Perfect10RequestContext;
use App\Services\Perfect10\PredictionContextService;
use App\Services\Perfect10\TeamContextService;
use Inertia\Inertia;
use Inertia\Response;

class GameweekController extends Controller
{
    public function __construct(
        private Perfect10RequestContext $context,
        private PredictionContextService $predictionContext,
        private CurrentProfileService $currentProfile,
        private TeamContextService $teamContext,
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

        $activeGameweek = $this->context->activeGameweek();

        /*
         * Every gameweek belongs to a stage.
         *
         * Retrieve its fixtures using the canonical stage ID.
         */
        $fixtures = $activeGameweek !== null
            ? $this->context->fixturesForStage(
                (int) $activeGameweek->stage_id
            )
            : [];

        $allPredictions = $this->predictionContext
            ->predictionsForFlavour(
                $flavour,
                $this->currentProfile->profileId()
            );

        $fixtureIds = collect($fixtures)
            ->pluck('fixture_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $predictionsMap = collect($allPredictions)
            ->only($fixtureIds)
            ->all();

        $teamsMap = $this->teamContext
            ->teamsMapForFixtures($fixtures);

        return Inertia::render('perfect10/gameweek', [
            'flavours' => $flavours,
            'flavour' => $selected,

            'activeGameweek' => $activeGameweek !== null
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

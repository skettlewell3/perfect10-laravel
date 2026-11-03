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

    public function index(string $flavour, ?string $week = null): Response
    {
        $flavours = $this->context->flavours();

        $selected = $this->context->selectedFlavourData();

        abort_unless(
            $selected['format_code'] === 'GWK',
            404,
            'The Gameweek page is only available for GWK formats.'
        );

        $flavourModel = $this->context->flavour();

        /*
         * Gameweeks are already cached by Perfect10RequestContext.
         * This collection belongs to the selected campaign.
         */
        $gameweeks = $this->context->gameweeks();

        $activeGameweek = $this->context->activeGameweek();

        /*
         * Without a week parameter, default to the active gameweek.
         * Otherwise resolve the requested gameweek by number.
         */
        if ($week === null) {
            $selectedGameweek = $activeGameweek;
        } else {
            abort_unless(
                ctype_digit($week) && (int) $week > 0,
                404,
                'Invalid gameweek number.'
            );

            $selectedGameweek = $gameweeks->first(
                fn ($gameweek): bool => (int) $gameweek->gameweek_number === (int) $week
            );

            abort_if(
                $selectedGameweek === null,
                404,
                'Gameweek not found in the selected campaign.'
            );
        }

        /*
         * Fixture data comes from the selected gameweek,
         * which may be historical, active or future.
         */
        $fixtures = $selectedGameweek !== null
            ? $this->context->fixturesForStage(
                (int) $selectedGameweek->stage_id
            )
            : [];

        /*
         * Saved predictions are read-only unless the frontend
         * is viewing the active gameweek during an open window.
         */
        $allPredictions = $this->predictionContext
            ->predictionsForFlavour(
                $flavourModel,
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

        $serializeGameweek = static fn ($gameweek): ?array => $gameweek !== null
                ? [
                    'gameweek_id' => (int) $gameweek->gameweek_id,
                    'gameweek_number' => (int) $gameweek->gameweek_number,
                    'status' => $gameweek->status,
                    'prediction_open_at' => $gameweek->prediction_open_at,
                    'prediction_close_at' => $gameweek->prediction_close_at,
                ]
                : null;

        return Inertia::render('perfect10/gameweek', [
            'flavours' => $flavours,
            'flavour' => $selected,

            'activeGameweek' => $serializeGameweek($activeGameweek),
            'selectedGameweek' => $serializeGameweek($selectedGameweek),

            'gameweeks' => $gameweeks
                ->map(fn ($gameweek): array => [
                    'gameweek_id' => (int) $gameweek->gameweek_id,
                    'gameweek_number' => (int) $gameweek->gameweek_number,
                    'status' => $gameweek->status,
                ])
                ->values()
                ->all(),

            'fixtures' => $fixtures,
            'teamsMap' => $teamsMap,
            'predictionsMap' => $predictionsMap,
        ]);
    }
}

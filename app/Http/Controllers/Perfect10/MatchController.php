<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\LeagueTableContextService;
use App\Services\Perfect10\MatchFormContextService;
use App\Services\Perfect10\Perfect10RequestContext;
use App\Services\Perfect10\PredictionContextService;
use App\Services\Perfect10\TeamContextService;
use Inertia\Inertia;
use Inertia\Response;

class MatchController extends Controller
{
    public function __construct(
        private Perfect10RequestContext $context,
        private PredictionContextService $predictionContext,
        private CurrentProfileService $currentProfile,
        private LeagueTableContextService $leagueTableContext,
        private TeamContextService $teamContext,
        private MatchFormContextService $matchFormContext,
    ) {}

    public function show(string $flavour, string $fixture): Response
    {
        /*
         * Both route parameters must be accepted in URL order:
         *
         * /{flavour}/matches/{fixture}
         *
         * The selected flavour itself is resolved through
         * Perfect10RequestContext.
         */
        abort_unless(
            ctype_digit($fixture) && (int) $fixture > 0,
            404,
            'Invalid fixture ID.'
        );

        $fixtureId = (int) $fixture;

        $selected = $this->context->selectedFlavourData();

        abort_unless(
            $selected['format_code'] === 'GWK',
            404,
            'Match Details is currently available for GWK formats only.'
        );

        $activeGameweek = $this->context->activeGameweek();

        abort_unless(
            $activeGameweek !== null,
            404,
            'No active gameweek is available.'
        );

        $flavourModel = $this->context->flavour();

        /*
         * The active gameweek's stage is the fixture grouping.
         *
         * These fixtures are used for match navigation and
         * gameweek prediction submission.
         */
        $fixtures = $this->context->fixturesForStage(
            (int) $activeGameweek->stage_id
        );

        $currentIndex = collect($fixtures)->search(
            fn (array $item): bool => (int) $item['fixture_id'] === $fixtureId
        );

        abort_if(
            $currentIndex === false,
            404,
            'This fixture is not part of the active gameweek.'
        );

        $currentFixture = $fixtures[$currentIndex];

        $previousFixture = $currentIndex > 0
            ? $fixtures[$currentIndex - 1]
            : null;

        $nextFixture = $currentIndex < count($fixtures) - 1
            ? $fixtures[$currentIndex + 1]
            : null;

        /*
         * Preserve predictions for the entire gameweek,
         * not just the selected fixture.
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

        /*
         * Keep the existing match fixture team data.
         */
        $teamsMap = $this->teamContext
            ->teamsMapForFixtures($fixtures);

        /*
         * Build the Form Guide using the complete fixture-provider
         * collection, not just the active gameweek.
         *
         * Perfect10RequestContext caches the fixture-provider
         * response for this request.
         */
        $form = $this->matchFormContext->forMatch(
            $this->context->fixtures(),
            $currentFixture
        );

        /*
         * Gather the selected historical and upcoming fixtures
         * so their opponents' colours can be displayed.
         */
        $formFixtures = [
            ...$form['home']['past'],
            ...$form['home']['future'],
            ...$form['away']['past'],
            ...$form['away']['future'],
        ];

        $formFixtures = array_values(
            collect($formFixtures)
                ->unique('fixture_id')
                ->all()
        );

        $formTeamsMap = $this->teamContext
            ->teamsMapForFixtures($formFixtures);

        /*
         * Retrieve the actual league table positions
         * of the two participating teams.
         */
        $homeTeamId = $currentFixture['home_team_id'];
        $awayTeamId = $currentFixture['away_team_id'];

        $teamIds = array_values(array_filter(
            [$homeTeamId, $awayTeamId],
            fn ($id): bool => $id !== null
        ));

        $teamIds = array_map('intval', $teamIds);

        $actualTable = $this->leagueTableContext
            ->actualTable((int) $selected['flavour_id'])
            ->filter(
                fn (array $row): bool => in_array(
                    (int) $row['team_id'],
                    $teamIds,
                    true
                )
            )
            ->sortBy('pos')
            ->values();

        return Inertia::render('perfect10/match', [
            'flavour' => $selected,

            'activeGameweek' => [
                'gameweek_id' => $activeGameweek->gameweek_id,
                'gameweek_number' => $activeGameweek->gameweek_number,
                'status' => $activeGameweek->status,
                'prediction_open_at' => $activeGameweek->prediction_open_at,
                'prediction_close_at' => $activeGameweek->prediction_close_at,
            ],

            'fixture' => $currentFixture,
            'fixtures' => $fixtures,
            'teamsMap' => $teamsMap,
            'predictionsMap' => $predictionsMap,
            'actualTable' => $actualTable,

            'form' => $form,
            'formTeamsMap' => $formTeamsMap,

            'navigation' => [
                'previousFixtureId' => $previousFixture['fixture_id'] ?? null,
                'nextFixtureId' => $nextFixture['fixture_id'] ?? null,
            ],
        ]);
    }
}

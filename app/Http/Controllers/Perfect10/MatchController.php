<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Models\Perfect10\Gameweek;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\LeagueTableContextService;
use App\Services\Perfect10\MatchFormContextService;
use App\Services\Perfect10\Perfect10RequestContext;
use App\Services\Perfect10\PredictionContextService;
use App\Services\Perfect10\TeamComparisonContextService;
use App\Services\Perfect10\TeamContextService;
use Illuminate\Support\Collection;
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
        private TeamComparisonContextService $teamComparisonContext,
    ) {}

    public function show(string $flavour, string $fixture): Response
    {
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

        $flavourModel = $this->context->flavour();

        /*
         * Retrieve fixtures belonging to the selected
         * flavour's active campaign.
         */
        $allFixtures = $this->context->fixtures();

        $currentFixture = collect($allFixtures)->first(
            fn (array $item): bool => (int) $item['fixture_id'] === $fixtureId
        );

        abort_if(
            $currentFixture === null,
            404,
            'This fixture is not part of the selected campaign.'
        );

        /*
         * Keep the currently active gameweek separate
         * from the selected fixture's gameweek.
         */
        $activeGameweek = $this->context->activeGameweek();
        $gameweeks = $this->context->gameweeks();

        $selectedGameweek = $this->resolveFixtureGameweek(
            $currentFixture,
            $gameweeks
        );

        /*
         * Regular fixtures navigate within their gameweek.
         * Standalone fixtures navigate within their stage.
         */
        $fixtures = $this->fixturesForMatchNavigation(
            $allFixtures,
            $currentFixture,
            $selectedGameweek
        );

        $currentIndex = collect($fixtures)->search(
            fn (array $item): bool => (int) $item['fixture_id'] === $fixtureId
        );

        abort_if(
            $currentIndex === false,
            404,
            'The selected fixture could not be resolved.'
        );

        /*
         * Collection::search() can return an integer
         * or string key. Navigation uses a sequential list,
         * so convert the resolved index to an integer.
         */
        $currentIndex = (int) $currentIndex;

        $currentFixture = $fixtures[$currentIndex];

        $previousFixture = $currentIndex > 0
            ? $fixtures[$currentIndex - 1]
            : null;

        $nextFixture = $currentIndex < count($fixtures) - 1
            ? $fixtures[$currentIndex + 1]
            : null;

        /*
         * Retrieve predictions for the current profile,
         * restricted to the selected navigation group.
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
         * Team presentation data for the selected group.
         */
        $teamsMap = $this->teamContext
            ->teamsMapForFixtures($fixtures);

        /*
         * Build the Form Guide using the complete campaign
         * fixture collection.
         */
        $form = $this->matchFormContext->forMatch(
            $allFixtures,
            $currentFixture
        );

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
         * Retrieve the current league positions
         * for the selected fixture's two teams.
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

        /*
         * Team Comparison returns the complete
         * Overall / Direct dataset.
         */
        $comparison = [];

        if ($homeTeamId !== null && $awayTeamId !== null) {
            $comparison = $this->teamComparisonContext->compare(
                (int) $selected['flavour_id'],
                (int) $homeTeamId,
                (int) $awayTeamId
            );
        }

        /*
         * Comparison results may reference opponents
         * from other gameweeks.
         */
        $comparisonTeamsMap = $this->teamContext
            ->teamsMapForFixtures($allFixtures);

        return Inertia::render('perfect10/match', [
            'flavour' => $selected,

            'activeGameweek' => $this->serializeGameweek(
                $activeGameweek
            ),

            'selectedGameweek' => $this->serializeGameweek(
                $selectedGameweek
            ),

            'fixture' => $currentFixture,
            'fixtures' => $fixtures,
            'teamsMap' => $teamsMap,
            'predictionsMap' => $predictionsMap,
            'actualTable' => $actualTable,

            'form' => $form,
            'formTeamsMap' => $formTeamsMap,

            'comparison' => $comparison,
            'comparisonTeamsMap' => $comparisonTeamsMap,

            'navigation' => [
                'previousFixtureId' => $previousFixture['fixture_id'] ?? null,
                'nextFixtureId' => $nextFixture['fixture_id'] ?? null,
            ],
        ]);
    }

    /**
     * Resolve the gameweek associated with a fixture.
     *
     * An explicitly null gameweek_id identifies a
     * standalone fixture, such as a rearranged match.
     *
     * @param  array<string, mixed>  $fixture
     * @param  Collection<int, Gameweek>  $gameweeks
     */
    private function resolveFixtureGameweek(
        array $fixture,
        Collection $gameweeks
    ): ?Gameweek {
        if (array_key_exists('gameweek_id', $fixture)) {
            if ($fixture['gameweek_id'] === null) {
                return null;
            }

            return $gameweeks->first(
                fn (Gameweek $gameweek): bool => (int) $gameweek->gameweek_id === (int) $fixture['gameweek_id']
            );
        }

        /*
         * Fallback when the fixture provider exposes
         * stage_id but does not expose gameweek_id.
         */
        if (($fixture['stage_id'] ?? null) === null) {
            return null;
        }

        return $gameweeks->first(
            fn (Gameweek $gameweek): bool => (int) $gameweek->stage_id === (int) $fixture['stage_id']
        );
    }

    /**
     * Build a sequential fixture list for MatchNav.
     *
     * @param  list<array<string, mixed>>  $allFixtures
     * @param  array<string, mixed>  $currentFixture
     * @return list<array<string, mixed>>
     */
    private function fixturesForMatchNavigation(
        array $allFixtures,
        array $currentFixture,
        ?Gameweek $selectedGameweek
    ): array {
        $fixtures = collect($allFixtures);

        if ($selectedGameweek !== null) {
            $fixtures = $fixtures->filter(
                function (array $fixture) use ($selectedGameweek): bool {
                    if (array_key_exists('gameweek_id', $fixture)) {
                        return $fixture['gameweek_id'] !== null
                            && (int) $fixture['gameweek_id'] === (int) $selectedGameweek->gameweek_id;
                    }

                    return ($fixture['stage_id'] ?? null) !== null
                        && (int) $fixture['stage_id'] === (int) $selectedGameweek->stage_id;
                }
            );
        } else {
            $stageId = $currentFixture['stage_id'] ?? null;

            $fixtures = $fixtures->filter(
                function (array $fixture) use ($stageId): bool {
                    if (array_key_exists('gameweek_id', $fixture)) {
                        if ($fixture['gameweek_id'] !== null) {
                            return false;
                        }
                    }

                    return $stageId !== null
                        && ($fixture['stage_id'] ?? null) !== null
                        && (int) $fixture['stage_id'] === (int) $stageId;
                }
            );
        }

        return array_values(
            $fixtures
                ->sort(function (array $a, array $b): int {
                    $kickoffOrder = strcmp(
                        (string) ($a['kickoff_at'] ?? ''),
                        (string) ($b['kickoff_at'] ?? '')
                    );

                    if ($kickoffOrder !== 0) {
                        return $kickoffOrder;
                    }

                    return (int) $a['fixture_id'] <=> (int) $b['fixture_id'];
                })
                ->all()
        );
    }

    /**
     * Serialize a gameweek consistently with the Gameweek page.
     *
     * @return array<string, mixed>|null
     */
    private function serializeGameweek(?Gameweek $gameweek): ?array
    {
        if ($gameweek === null) {
            return null;
        }

        return [
            'gameweek_id' => $gameweek->gameweek_id,
            'gameweek_number' => $gameweek->gameweek_number,
            'status' => $gameweek->status,
            'prediction_open_at' => $gameweek->prediction_open_at,
            'prediction_close_at' => $gameweek->prediction_close_at,
        ];
    }
}

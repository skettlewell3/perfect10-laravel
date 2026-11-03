
<?php

use App\Models\Perfect10\Flavour;
use App\Models\Perfect10\Gameweek;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\LeagueTableContextService;
use App\Services\Perfect10\MatchFormContextService;
use App\Services\Perfect10\Perfect10RequestContext;
use App\Services\Perfect10\PredictionContextService;
use App\Services\Perfect10\TeamComparisonContextService;
use App\Services\Perfect10\TeamContextService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();

    if (
        DB::connection()->getDriverName() !== 'sqlite' ||
        DB::connection()->getDatabaseName() !== ':memory:'
    ) {
        throw new RuntimeException(
            'Match controller tests require in-memory SQLite.'
        );
    }

    $currentProfile = Mockery::mock(
        CurrentProfileService::class
    )->shouldIgnoreMissing();

    $currentProfile
        ->shouldReceive('profileId')
        ->andReturn(null);

    $currentProfile
        ->shouldReceive('userProfile')
        ->andReturn(null);

    app()->instance(
        CurrentProfileService::class,
        $currentProfile
    );
});

/**
 * Fixtures belonging to the active gameweek.
 *
 * @return list<array<string, mixed>>
 */
function matchControllerFixtures(): array
{
    return [
        [
            'fixture_id' => 492,
            'stage_id' => 17,
            'gameweek_id' => 8,
            'kickoff_at' => '2026-10-23 17:00:00',
            'fixture_status' => 'upcoming',
            'home_team_id' => 1,
            'home_team_name' => 'Arsenal',
            'home_short_code' => 'ARS',
            'away_team_id' => 2,
            'away_team_name' => 'Chelsea',
            'away_short_code' => 'CHE',
        ],
        [
            'fixture_id' => 493,
            'stage_id' => 17,
            'gameweek_id' => 8,
            'kickoff_at' => '2026-10-23 19:00:00',
            'fixture_status' => 'upcoming',
            'home_team_id' => 3,
            'home_team_name' => 'Ipswich',
            'home_short_code' => 'IPS',
            'away_team_id' => 4,
            'away_team_name' => 'Nottingham Forest',
            'away_short_code' => 'NFO',
        ],
        [
            'fixture_id' => 494,
            'stage_id' => 17,
            'gameweek_id' => 8,
            'kickoff_at' => '2026-10-24 15:00:00',
            'fixture_status' => 'upcoming',
            'home_team_id' => 5,
            'home_team_name' => 'Hull',
            'home_short_code' => 'HUL',
            'away_team_id' => 6,
            'away_team_name' => 'Brentford',
            'away_short_code' => 'BRE',
        ],
    ];
}

/**
 * All fixtures in the selected campaign.
 *
 * Includes:
 * - A historical gameweek fixture.
 * - Three active gameweek fixtures.
 * - A future gameweek fixture.
 * - Two rearranged fixtures without gameweeks.
 *
 * @return list<array<string, mixed>>
 */
function matchControllerAllFixtures(): array
{
    return [
        [
            'fixture_id' => 400,
            'stage_id' => 16,
            'gameweek_id' => 7,
            'kickoff_at' => '2026-10-17 15:00:00',
            'fixture_status' => 'finished',
            'home_team_id' => 3,
            'home_team_name' => 'Ipswich',
            'home_short_code' => 'IPS',
            'away_team_id' => 7,
            'away_team_name' => 'Fulham',
            'away_short_code' => 'FUL',
            'final_home_goals' => 2,
            'final_away_goals' => 1,
            'resolution_type' => 'home_win',
        ],
        ...matchControllerFixtures(),
        [
            'fixture_id' => 550,
            'stage_id' => 18,
            'gameweek_id' => 9,
            'kickoff_at' => '2026-10-31 15:00:00',
            'fixture_status' => 'upcoming',
            'home_team_id' => 8,
            'home_team_name' => 'Everton',
            'home_short_code' => 'EVE',
            'away_team_id' => 4,
            'away_team_name' => 'Nottingham Forest',
            'away_short_code' => 'NFO',
        ],
        [
            'fixture_id' => 600,
            'stage_id' => 19,
            'gameweek_id' => null,
            'kickoff_at' => '2026-11-10 19:45:00',
            'fixture_status' => 'upcoming',
            'home_team_id' => 1,
            'home_team_name' => 'Arsenal',
            'home_short_code' => 'ARS',
            'away_team_id' => 8,
            'away_team_name' => 'Everton',
            'away_short_code' => 'EVE',
        ],
        [
            'fixture_id' => 601,
            'stage_id' => 19,
            'gameweek_id' => null,
            'kickoff_at' => '2026-11-11 19:45:00',
            'fixture_status' => 'upcoming',
            'home_team_id' => 2,
            'home_team_name' => 'Chelsea',
            'home_short_code' => 'CHE',
            'away_team_id' => 6,
            'away_team_name' => 'Brentford',
            'away_short_code' => 'BRE',
        ],
    ];
}

/**
 * @return array{
 *     home: array{past: list<array<string, mixed>>, future: list<array<string, mixed>>},
 *     away: array{past: list<array<string, mixed>>, future: list<array<string, mixed>>}
 * }
 */
function matchControllerForm(): array
{
    $allFixtures = matchControllerAllFixtures();

    return [
        'home' => [
            'past' => [$allFixtures[0]],
            'future' => [],
        ],
        'away' => [
            'past' => [],
            'future' => [$allFixtures[4]],
        ],
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function matchControllerComparison(): array
{
    return [
        [
            'opponent_id' => 7,
            'opponent_name' => 'Fulham',
            'opponent_short' => 'FUL',
            't1_home_gf' => 2,
            't1_home_ga' => 1,
            't1_home_pts' => 3,
            't1_home_swing' => null,
            't2_home_gf' => null,
            't2_home_ga' => null,
            't2_home_pts' => null,
            't2_home_swing' => null,
        ],
    ];
}

/**
 * Create the gameweeks belonging to the selected campaign.
 */
function matchControllerGameweeks(
    bool $hasActiveGameweek = true
): EloquentCollection {
    return new EloquentCollection([
        new Gameweek([
            'gameweek_id' => 7,
            'campaign_id' => 100,
            'stage_id' => 16,
            'gameweek_number' => 7,
            'status' => 'finished',
        ]),
        new Gameweek([
            'gameweek_id' => 8,
            'campaign_id' => 100,
            'stage_id' => 17,
            'gameweek_number' => 8,
            'status' => $hasActiveGameweek ? 'active' : 'finished',
            'prediction_open_at' => '2026-10-16 12:00:00',
            'prediction_close_at' => '2026-10-23 18:30:00',
        ]),
        new Gameweek([
            'gameweek_id' => 9,
            'campaign_id' => 100,
            'stage_id' => 18,
            'gameweek_number' => 9,
            'status' => 'upcoming',
        ]),
    ]);
}

/**
 * Configure the selected flavour and request context.
 */
function setupMatchControllerContext(
    string $formatCode = 'GWK',
    bool $hasActiveGameweek = true
): ?Flavour {
    $selected = [
        'flavour_id' => $formatCode === 'GWK' ? 1 : 2,
        'flavour_name' => $formatCode === 'GWK'
            ? 'Premier League'
            : 'World Cup',
        'flavour_code' => $formatCode === 'GWK' ? 'EPL' : 'WC',
        'format_code' => $formatCode,
        'active_campaign_id' => $formatCode === 'GWK' ? 100 : 200,
    ];

    $context = Mockery::mock(
        Perfect10RequestContext::class
    );

    $context
        ->shouldReceive('selectedFlavourData')
        ->once()
        ->andReturn($selected);

    /*
     * PFX formats must be rejected before requesting
     * GWK-specific context or fixture data.
     */
    if ($formatCode !== 'GWK') {
        $context->shouldNotReceive('flavour');
        $context->shouldNotReceive('fixtures');
        $context->shouldNotReceive('gameweeks');
        $context->shouldNotReceive('activeGameweek');
        $context->shouldNotReceive('fixturesForStage');

        app()->instance(
            Perfect10RequestContext::class,
            $context
        );

        return null;
    }

    $flavour = new Flavour([
        'flavour_id' => 1,
        'flavour_code' => 'EPL',
    ]);

    $gameweeks = matchControllerGameweeks(
        $hasActiveGameweek
    );

    $activeGameweek = $hasActiveGameweek
        ? $gameweeks->first(
            fn (Gameweek $gameweek): bool => $gameweek->status === 'active'
        )
        : null;

    $context
        ->shouldReceive('flavour')
        ->once()
        ->andReturn($flavour);

    $context
        ->shouldReceive('fixtures')
        ->once()
        ->andReturn(matchControllerAllFixtures());

    $context
        ->shouldReceive('activeGameweek')
        ->between(0, 1)
        ->andReturn($activeGameweek);

    $context
        ->shouldReceive('gameweeks')
        ->between(0, 1)
        ->andReturn($gameweeks);

    $context->shouldNotReceive('fixturesForStage');

    app()->instance(
        Perfect10RequestContext::class,
        $context
    );

    return $flavour;
}

/**
 * Retrieve a fixture from the test campaign.
 *
 * @return array<string, mixed>
 */
function matchControllerFixture(int $fixtureId): array
{
    $fixture = collect(matchControllerAllFixtures())
        ->first(
            fn (array $row): bool => (int) $row['fixture_id'] === $fixtureId
        );

    if ($fixture === null) {
        throw new RuntimeException(
            "Test fixture {$fixtureId} does not exist."
        );
    }

    return $fixture;
}

/**
 * Return a navigation group for a selected fixture.
 *
 * @return list<array<string, mixed>>
 */
function matchControllerNavigationFixtures(int $fixtureId): array
{
    $currentFixture = matchControllerFixture($fixtureId);

    $gameweekId = $currentFixture['gameweek_id'];
    $stageId = $currentFixture['stage_id'];

    return collect(matchControllerAllFixtures())
        ->filter(function (array $fixture) use (
            $gameweekId,
            $stageId
        ): bool {
            if ($gameweekId !== null) {
                return $fixture['gameweek_id'] !== null
                    && (int) $fixture['gameweek_id'] === (int) $gameweekId;
            }

            return $fixture['gameweek_id'] === null
                && (int) $fixture['stage_id'] === (int) $stageId;
        })
        ->sortBy([
            ['kickoff_at', 'asc'],
            ['fixture_id', 'asc'],
        ])
        ->values()
        ->all();
}

/**
 * Return representative team presentation data.
 */
function matchControllerTeamsMap(array $fixtures): Collection
{
    $teams = [
        1 => ['team_name' => 'Arsenal', 'color_1' => '#ff0000'],
        2 => ['team_name' => 'Chelsea', 'color_1' => '#0000ff'],
        3 => ['team_name' => 'Ipswich', 'color_1' => '#1234ff'],
        4 => ['team_name' => 'Nottingham Forest', 'color_1' => '#ff0000'],
        5 => ['team_name' => 'Hull', 'color_1' => '#ff9900'],
        6 => ['team_name' => 'Brentford', 'color_1' => '#dd0000'],
        7 => ['team_name' => 'Fulham', 'color_1' => '#ffffff'],
        8 => ['team_name' => 'Everton', 'color_1' => '#0000aa'],
    ];

    $teamIds = collect($fixtures)
        ->flatMap(fn (array $fixture): array => [
            $fixture['home_team_id'],
            $fixture['away_team_id'],
        ])
        ->filter(fn ($id): bool => $id !== null)
        ->unique()
        ->values();

    return $teamIds->mapWithKeys(
        function ($teamId) use ($teams): array {
            $id = (int) $teamId;

            return [
                $id => [
                    'team_id' => $id,
                    'team_name' => $teams[$id]['team_name'],
                    'flag_code' => null,
                    'color_1' => $teams[$id]['color_1'],
                    'color_2' => '#ffffff',
                    'color_3' => null,
                ],
            ];
        }
    );
}

/**
 * Return representative league table data.
 */
function matchControllerActualTable(): Collection
{
    return collect([
        [
            'team_id' => 1,
            'team_name' => 'Arsenal',
            'pos' => 2,
            'points' => 20,
        ],
        [
            'team_id' => 2,
            'team_name' => 'Chelsea',
            'pos' => 5,
            'points' => 15,
        ],
        [
            'team_id' => 3,
            'team_name' => 'Ipswich',
            'pos' => 10,
            'points' => 9,
        ],
        [
            'team_id' => 4,
            'team_name' => 'Nottingham Forest',
            'pos' => 19,
            'points' => 5,
        ],
        [
            'team_id' => 5,
            'team_name' => 'Hull',
            'pos' => 9,
            'points' => 10,
        ],
        [
            'team_id' => 6,
            'team_name' => 'Brentford',
            'pos' => 6,
            'points' => 12,
        ],
        [
            'team_id' => 7,
            'team_name' => 'Fulham',
            'pos' => 8,
            'points' => 13,
        ],
        [
            'team_id' => 8,
            'team_name' => 'Everton',
            'pos' => 12,
            'points' => 8,
        ],
    ]);
}

/**
 * Set up the dependencies required to render any selected match.
 *
 * The fixture ID controls the expected navigation group,
 * form context and team comparison arguments.
 */
function setupMatchControllerServices(
    Flavour $flavour,
    int $fixtureId,
    bool $includeFormData = false
): void {
    $currentFixture = matchControllerFixture($fixtureId);

    $navigationFixtures = matchControllerNavigationFixtures(
        $fixtureId
    );

    $allFixtures = matchControllerAllFixtures();

    /*
     * Predictions are returned for the selected flavour.
     * The controller must then restrict them to the
     * selected fixture's navigation group.
     */
    $predictionService = Mockery::mock(
        PredictionContextService::class
    );

    $predictionService
        ->shouldReceive('predictionsForFlavour')
        ->once()
        ->with($flavour, null)
        ->andReturn([
            400 => [
                'fixture_id' => 400,
                'pred_home_goals' => 2,
                'pred_away_goals' => 1,
            ],
            492 => [
                'fixture_id' => 492,
                'pred_home_goals' => 2,
                'pred_away_goals' => 1,
            ],
            493 => [
                'fixture_id' => 493,
                'pred_home_goals' => 1,
                'pred_away_goals' => 0,
            ],
            494 => [
                'fixture_id' => 494,
                'pred_home_goals' => 0,
                'pred_away_goals' => 0,
            ],
            600 => [
                'fixture_id' => 600,
                'pred_home_goals' => 1,
                'pred_away_goals' => 2,
            ],
        ]);

    app()->instance(
        PredictionContextService::class,
        $predictionService
    );

    /*
     * Form Guide.
     */
    $form = $includeFormData
        ? matchControllerForm()
        : [
            'home' => [
                'past' => [],
                'future' => [],
            ],
            'away' => [
                'past' => [],
                'future' => [],
            ],
        ];

    $formService = Mockery::mock(
        MatchFormContextService::class
    );

    $formService
        ->shouldReceive('forMatch')
        ->once()
        ->with($allFixtures, $currentFixture)
        ->andReturn($form);

    app()->instance(
        MatchFormContextService::class,
        $formService
    );

    /*
     * Teams for navigation fixtures, form fixtures
     * and the full comparison dataset.
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

    $teamService = Mockery::mock(
        TeamContextService::class
    );

    $teamService
        ->shouldReceive('teamsMapForFixtures')
        ->once()
        ->with($navigationFixtures)
        ->andReturn(
            matchControllerTeamsMap($navigationFixtures)
        );

    $teamService
        ->shouldReceive('teamsMapForFixtures')
        ->once()
        ->with($formFixtures)
        ->andReturn(
            matchControllerTeamsMap($formFixtures)
        );

    $teamService
        ->shouldReceive('teamsMapForFixtures')
        ->once()
        ->with($allFixtures)
        ->andReturn(
            matchControllerTeamsMap($allFixtures)
        );

    app()->instance(
        TeamContextService::class,
        $teamService
    );

    /*
     * Actual league table.
     */
    $leagueTableService = Mockery::mock(
        LeagueTableContextService::class
    );

    $leagueTableService
        ->shouldReceive('actualTable')
        ->once()
        ->with(1)
        ->andReturn(matchControllerActualTable());

    app()->instance(
        LeagueTableContextService::class,
        $leagueTableService
    );

    /*
     * Team Comparison.
     */
    $comparisonService = Mockery::mock(
        TeamComparisonContextService::class
    );

    $comparisonService
        ->shouldReceive('compare')
        ->once()
        ->with(
            1,
            (int) $currentFixture['home_team_id'],
            (int) $currentFixture['away_team_id']
        )
        ->andReturn(
            $includeFormData
                ? matchControllerComparison()
                : []
        );

    app()->instance(
        TeamComparisonContextService::class,
        $comparisonService
    );
}

test('match details returns the selected fixture with predictions teams league positions and form', function () {
    $flavour = setupMatchControllerContext();

    setupMatchControllerServices(
        $flavour,
        493,
        true
    );

    $response = $this->get(
        route('matches.show', [
            'flavour' => 'epl',
            'fixture' => 493,
        ])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->component('perfect10/match')
            ->where('flavour.flavour_code', 'EPL')
            ->where('activeGameweek.gameweek_id', 8)
            ->where('activeGameweek.gameweek_number', 8)
            ->where('selectedGameweek.gameweek_id', 8)
            ->where('selectedGameweek.gameweek_number', 8)
            ->where('fixture.fixture_id', 493)
            ->where('fixture.home_team_name', 'Ipswich')
            ->where('fixture.away_team_name', 'Nottingham Forest')
            ->has('fixtures', 3)
            ->where('fixtures.0.fixture_id', 492)
            ->where('fixtures.1.fixture_id', 493)
            ->where('fixtures.2.fixture_id', 494)
            ->has('predictionsMap', 3)
            ->where('predictionsMap.492.pred_home_goals', 2)
            ->where('predictionsMap.493.pred_home_goals', 1)
            ->where('predictionsMap.494.pred_away_goals', 0)
            ->missing('predictionsMap.400')
            ->missing('predictionsMap.600')
            ->has('teamsMap', 6)
            ->where('teamsMap.3.team_name', 'Ipswich')
            ->where('teamsMap.4.team_name', 'Nottingham Forest')
            ->has('actualTable', 2)
            ->where('actualTable.0.team_id', 3)
            ->where('actualTable.0.pos', 10)
            ->where('actualTable.1.team_id', 4)
            ->where('actualTable.1.pos', 19)
            ->has('form.home.past', 1)
            ->where('form.home.past.0.fixture_id', 400)
            ->has('form.home.future', 0)
            ->has('form.away.past', 0)
            ->has('form.away.future', 1)
            ->where('form.away.future.0.fixture_id', 550)
            ->where('form.home.past.0.resolution_type', 'home_win')
            ->where('formTeamsMap.7.team_name', 'Fulham')
            ->where('formTeamsMap.8.team_name', 'Everton')
            ->has('comparison', 1)
            ->where('comparison.0.opponent_id', 7)
            ->where('comparison.0.t1_home_pts', 3)
            ->where('comparisonTeamsMap.7.team_name', 'Fulham')
            ->etc();
    });
});

test('match navigation uses the correct previous and next fixture IDs', function (
    int $fixtureId,
    ?int $previousId,
    ?int $nextId
) {
    $flavour = setupMatchControllerContext();

    setupMatchControllerServices(
        $flavour,
        $fixtureId
    );

    $response = $this->get(
        route('matches.show', [
            'flavour' => 'epl',
            'fixture' => $fixtureId,
        ])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) use (
        $fixtureId,
        $previousId,
        $nextId
    ) {
        $page
            ->component('perfect10/match')
            ->where('fixture.fixture_id', $fixtureId)
            ->where('selectedGameweek.gameweek_number', 8)
            ->where('navigation.previousFixtureId', $previousId)
            ->where('navigation.nextFixtureId', $nextId)
            ->etc();
    });
})->with([
    'first fixture' => [492, null, 493],
    'middle fixture' => [493, 492, 494],
    'last fixture' => [494, 493, null],
]);

test('historical gameweek fixture remains accessible', function () {
    $flavour = setupMatchControllerContext();

    setupMatchControllerServices(
        $flavour,
        400
    );

    $response = $this->get(
        route('matches.show', [
            'flavour' => 'epl',
            'fixture' => 400,
        ])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->component('perfect10/match')
            ->where('activeGameweek.gameweek_number', 8)
            ->where('selectedGameweek.gameweek_number', 7)
            ->where('selectedGameweek.status', 'finished')
            ->where('fixture.fixture_id', 400)
            ->has('fixtures', 1)
            ->has('predictionsMap', 1)
            ->where('predictionsMap.400.pred_home_goals', 2)
            ->where('navigation.previousFixtureId', null)
            ->where('navigation.nextFixtureId', null)
            ->etc();
    });
});

test('future gameweek fixture remains accessible', function () {
    $flavour = setupMatchControllerContext();

    setupMatchControllerServices(
        $flavour,
        550
    );

    $response = $this->get(
        route('matches.show', [
            'flavour' => 'epl',
            'fixture' => 550,
        ])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->component('perfect10/match')
            ->where('activeGameweek.gameweek_number', 8)
            ->where('selectedGameweek.gameweek_number', 9)
            ->where('selectedGameweek.status', 'upcoming')
            ->where('fixture.fixture_id', 550)
            ->has('fixtures', 1)
            ->has('predictionsMap', 0)
            ->where('navigation.previousFixtureId', null)
            ->where('navigation.nextFixtureId', null)
            ->etc();
    });
});

test('match details remains accessible when no gameweek is active', function () {
    $flavour = setupMatchControllerContext(
        'GWK',
        false
    );

    setupMatchControllerServices(
        $flavour,
        493
    );

    $response = $this->get(
        route('matches.show', [
            'flavour' => 'epl',
            'fixture' => 493,
        ])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->component('perfect10/match')
            ->where('activeGameweek', null)
            ->where('selectedGameweek.gameweek_number', 8)
            ->where('selectedGameweek.status', 'finished')
            ->where('fixture.fixture_id', 493)
            ->has('fixtures', 3)
            ->etc();
    });
});

test('rearranged fixtures without gameweeks remain accessible', function () {
    $flavour = setupMatchControllerContext();

    setupMatchControllerServices(
        $flavour,
        600
    );

    $response = $this->get(
        route('matches.show', [
            'flavour' => 'epl',
            'fixture' => 600,
        ])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->component('perfect10/match')
            ->where('activeGameweek.gameweek_number', 8)
            ->where('selectedGameweek', null)
            ->where('fixture.fixture_id', 600)
            ->where('fixture.gameweek_id', null)
            ->has('fixtures', 2)
            ->where('fixtures.0.fixture_id', 600)
            ->where('fixtures.1.fixture_id', 601)
            ->has('predictionsMap', 1)
            ->where('predictionsMap.600.pred_home_goals', 1)
            ->where('navigation.previousFixtureId', null)
            ->where('navigation.nextFixtureId', 601)
            ->etc();
    });
});

test('rearranged fixture navigation remains within its standalone stage', function () {
    $flavour = setupMatchControllerContext();

    setupMatchControllerServices(
        $flavour,
        601
    );

    $response = $this->get(
        route('matches.show', [
            'flavour' => 'epl',
            'fixture' => 601,
        ])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->component('perfect10/match')
            ->where('selectedGameweek', null)
            ->where('fixture.fixture_id', 601)
            ->has('fixtures', 2)
            ->where('navigation.previousFixtureId', 600)
            ->where('navigation.nextFixtureId', null)
            ->etc();
    });
});

test('fixture outside the selected campaign returns not found', function () {
    setupMatchControllerContext();

    $comparisonService = Mockery::mock(
        TeamComparisonContextService::class
    );

    $comparisonService->shouldNotReceive('compare');

    app()->instance(
        TeamComparisonContextService::class,
        $comparisonService
    );

    $response = $this->get(
        route('matches.show', [
            'flavour' => 'epl',
            'fixture' => 999,
        ])
    );

    $response->assertNotFound();
});

test('zero fixture IDs are rejected', function () {
    $response = $this->get(
        route('matches.show', [
            'flavour' => 'epl',
            'fixture' => 0,
        ])
    );

    $response->assertNotFound();
});

test('per fixture flavours cannot access GWK match details', function () {
    setupMatchControllerContext('PFX');

    $response = $this->get(
        route('matches.show', [
            'flavour' => 'wc',
            'fixture' => 493,
        ])
    );

    $response->assertNotFound();
});

test('non numeric fixture IDs are rejected by the route', function () {
    $response = $this->get(
        '/epl/matches/not-a-fixture'
    );

    $response->assertNotFound();
});

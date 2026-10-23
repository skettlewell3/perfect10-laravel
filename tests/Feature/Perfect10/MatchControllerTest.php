<?php

use App\Models\Perfect10\Flavour;
use App\Models\Perfect10\Gameweek;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\LeagueTableContextService;
use App\Services\Perfect10\MatchFormContextService;
use App\Services\Perfect10\Perfect10RequestContext;
use App\Services\Perfect10\PredictionContextService;
use App\Services\Perfect10\TeamContextService;
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

    if ($formatCode !== 'GWK') {
        $context->shouldNotReceive('activeGameweek');
        $context->shouldNotReceive('fixturesForStage');
        $context->shouldNotReceive('fixtures');
        $context->shouldNotReceive('flavour');

        app()->instance(
            Perfect10RequestContext::class,
            $context
        );

        return null;
    }

    $gameweek = $hasActiveGameweek
        ? new Gameweek([
            'gameweek_id' => 8,
            'campaign_id' => 100,
            'stage_id' => 17,
            'gameweek_number' => 8,
            'status' => 'active',
            'prediction_open_at' => '2026-10-16 12:00:00',
            'prediction_close_at' => '2026-10-23 18:30:00',
        ])
        : null;

    $context
        ->shouldReceive('activeGameweek')
        ->once()
        ->andReturn($gameweek);

    if (! $hasActiveGameweek) {
        $context->shouldNotReceive('fixturesForStage');
        $context->shouldNotReceive('fixtures');
        $context->shouldNotReceive('flavour');

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

    $context
        ->shouldReceive('flavour')
        ->once()
        ->andReturn($flavour);

    $context
        ->shouldReceive('fixturesForStage')
        ->once()
        ->with(17)
        ->andReturn(matchControllerFixtures());

    $context
        ->shouldReceive('fixtures')
        ->zeroOrMoreTimes()
        ->andReturn(matchControllerAllFixtures());

    app()->instance(
        Perfect10RequestContext::class,
        $context
    );

    return $flavour;
}

function setupMatchControllerServices(Flavour $flavour): void
{
    $predictionService = Mockery::mock(
        PredictionContextService::class
    );

    $predictionService
        ->shouldReceive('predictionsForFlavour')
        ->once()
        ->with($flavour, null)
        ->andReturn([
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
            400 => [
                'fixture_id' => 400,
                'pred_home_goals' => 2,
                'pred_away_goals' => 1,
            ],
        ]);

    app()->instance(
        PredictionContextService::class,
        $predictionService
    );

    $formService = Mockery::mock(
        MatchFormContextService::class
    );

    $formService
        ->shouldReceive('forMatch')
        ->once()
        ->with(
            matchControllerAllFixtures(),
            matchControllerFixtures()[1]
        )
        ->andReturn(matchControllerForm());

    app()->instance(
        MatchFormContextService::class,
        $formService
    );

    $teamService = Mockery::mock(
        TeamContextService::class
    );

    $teamService
        ->shouldReceive('teamsMapForFixtures')
        ->once()
        ->with(matchControllerFixtures())
        ->andReturn(collect([
            1 => [
                'team_id' => 1,
                'team_name' => 'Arsenal',
                'flag_code' => null,
                'color_1' => '#ff0000',
                'color_2' => '#ffffff',
                'color_3' => null,
            ],
            2 => [
                'team_id' => 2,
                'team_name' => 'Chelsea',
                'flag_code' => null,
                'color_1' => '#0000ff',
                'color_2' => '#ffffff',
                'color_3' => null,
            ],
            3 => [
                'team_id' => 3,
                'team_name' => 'Ipswich',
                'flag_code' => null,
                'color_1' => '#1234ff',
                'color_2' => '#ffffff',
                'color_3' => null,
            ],
            4 => [
                'team_id' => 4,
                'team_name' => 'Nottingham Forest',
                'flag_code' => null,
                'color_1' => '#ff0000',
                'color_2' => '#ffffff',
                'color_3' => null,
            ],
            5 => [
                'team_id' => 5,
                'team_name' => 'Hull',
                'flag_code' => null,
                'color_1' => '#ff9900',
                'color_2' => '#000000',
                'color_3' => null,
            ],
            6 => [
                'team_id' => 6,
                'team_name' => 'Brentford',
                'flag_code' => null,
                'color_1' => '#dd0000',
                'color_2' => '#ffffff',
                'color_3' => null,
            ],
        ]));

    $teamService
        ->shouldReceive('teamsMapForFixtures')
        ->once()
        ->with([
            matchControllerAllFixtures()[0],
            matchControllerAllFixtures()[4],
        ])
        ->andReturn(collect([
            3 => [
                'team_id' => 3,
                'team_name' => 'Ipswich',
                'flag_code' => null,
                'color_1' => '#1234ff',
                'color_2' => '#ffffff',
                'color_3' => null,
            ],
            4 => [
                'team_id' => 4,
                'team_name' => 'Nottingham Forest',
                'flag_code' => null,
                'color_1' => '#ff0000',
                'color_2' => '#ffffff',
                'color_3' => null,
            ],
            7 => [
                'team_id' => 7,
                'team_name' => 'Fulham',
                'flag_code' => null,
                'color_1' => '#ffffff',
                'color_2' => '#000000',
                'color_3' => null,
            ],
            8 => [
                'team_id' => 8,
                'team_name' => 'Everton',
                'flag_code' => null,
                'color_1' => '#0000aa',
                'color_2' => '#ffffff',
                'color_3' => null,
            ],
        ]));

    app()->instance(
        TeamContextService::class,
        $teamService
    );

    $leagueTableService = Mockery::mock(
        LeagueTableContextService::class
    );

    $leagueTableService
        ->shouldReceive('actualTable')
        ->once()
        ->with(1)
        ->andReturn(collect([
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
        ]));

    app()->instance(
        LeagueTableContextService::class,
        $leagueTableService
    );
}

test('match details returns the selected fixture with predictions teams league positions and form', function () {
    $flavour = setupMatchControllerContext();

    setupMatchControllerServices($flavour);

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
            ->etc();
    });
});

test('match navigation uses the correct previous and next fixture IDs', function (
    int $fixtureId,
    ?int $previousId,
    ?int $nextId
) {
    $flavour = setupMatchControllerContext();

    $formService = Mockery::mock(
        MatchFormContextService::class
    );

    $formService
        ->shouldReceive('forMatch')
        ->once()
        ->with(
            matchControllerAllFixtures(),
            Mockery::on(
                fn (array $fixture): bool => (
                    (int) $fixture['fixture_id'] === $fixtureId
                )
            )
        )
        ->andReturn([
            'home' => ['past' => [], 'future' => []],
            'away' => ['past' => [], 'future' => []],
        ]);

    app()->instance(
        MatchFormContextService::class,
        $formService
    );

    setupMatchControllerServicesForNavigation($flavour);

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
            ->where('navigation.previousFixtureId', $previousId)
            ->where('navigation.nextFixtureId', $nextId)
            ->etc();
    });
})->with([
    'first fixture' => [492, null, 493],
    'middle fixture' => [493, 492, 494],
    'last fixture' => [494, 493, null],
]);

/**
 * Setup shared non-form dependencies for navigation tests.
 */
function setupMatchControllerServicesForNavigation(Flavour $flavour): void
{
    $predictions = Mockery::mock(
        PredictionContextService::class
    );

    $predictions
        ->shouldReceive('predictionsForFlavour')
        ->once()
        ->with($flavour, null)
        ->andReturn([]);

    app()->instance(
        PredictionContextService::class,
        $predictions
    );

    $teams = Mockery::mock(
        TeamContextService::class
    );

    $teams
        ->shouldReceive('teamsMapForFixtures')
        ->once()
        ->with(matchControllerFixtures())
        ->andReturn(collect([]));

    $teams
        ->shouldReceive('teamsMapForFixtures')
        ->once()
        ->with([])
        ->andReturn(collect([]));

    app()->instance(
        TeamContextService::class,
        $teams
    );

    $leagueTable = Mockery::mock(
        LeagueTableContextService::class
    );

    $leagueTable
        ->shouldReceive('actualTable')
        ->once()
        ->with(1)
        ->andReturn(collect([]));

    app()->instance(
        LeagueTableContextService::class,
        $leagueTable
    );
}

test('fixture outside the active stage returns not found', function () {
    setupMatchControllerContext();

    $response = $this->get(
        route('matches.show', [
            'flavour' => 'epl',
            'fixture' => 999,
        ])
    );

    $response->assertNotFound();
});

test('match details returns not found when no active gameweek exists', function () {
    setupMatchControllerContext('GWK', false);

    $response = $this->get(
        route('matches.show', [
            'flavour' => 'epl',
            'fixture' => 493,
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
    $response = $this->get('/epl/matches/not-a-fixture');

    $response->assertNotFound();
});

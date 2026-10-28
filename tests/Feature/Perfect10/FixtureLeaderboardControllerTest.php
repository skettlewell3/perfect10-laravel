<?php

use App\Services\Perfect10\FixtureLeaderboardContextService;
use App\Services\Perfect10\Perfect10RequestContext;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();

    if (
        DB::connection()->getDriverName() !== 'sqlite' ||
        DB::connection()->getDatabaseName() !== ':memory:'
    ) {
        throw new RuntimeException(
            'Fixture leaderboard controller tests require in-memory SQLite.'
        );
    }
});

/**
 * Build a fixture with the fields required by the controller.
 *
 * @return array<string, mixed>
 */
function fixtureLeaderboardTestFixture(
    int $fixtureId,
    string $status
): array {
    return [
        'fixture_id' => $fixtureId,
        'fixture_status' => $status,
    ];
}

/**
 * Mock the selected flavour's fixture collection.
 *
 * @param  list<array<string, mixed>>  $fixtures
 */
function setupFixtureLeaderboardContext(array $fixtures): void
{
    $context = Mockery::mock(
        Perfect10RequestContext::class
    );

    $context
        ->shouldReceive('fixtures')
        ->once()
        ->andReturn($fixtures);

    app()->instance(
        Perfect10RequestContext::class,
        $context
    );
}

/**
 * Mock the leaderboard service.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function setupFixtureLeaderboardService(
    int $fixtureId,
    array $rows
): void {
    $service = Mockery::mock(
        FixtureLeaderboardContextService::class
    );

    $service
        ->shouldReceive('leaderboardForFixture')
        ->once()
        ->with($fixtureId)
        ->andReturn($rows);

    app()->instance(
        FixtureLeaderboardContextService::class,
        $service
    );
}

/**
 * Verify that ineligible requests never fetch leaderboard data.
 */
function preventFixtureLeaderboardServiceCall(): void
{
    $service = Mockery::mock(
        FixtureLeaderboardContextService::class
    );

    $service->shouldNotReceive('leaderboardForFixture');

    app()->instance(
        FixtureLeaderboardContextService::class,
        $service
    );
}

test('returns the fixture leaderboard for an eligible fixture', function (
    string $status
) {
    setupFixtureLeaderboardContext([
        fixtureLeaderboardTestFixture(493, $status),
    ]);

    $rows = [
        [
            'rank' => 1,
            'profile_id' => '11111111-1111-4111-8111-111111111111',
            'display_name' => 'Player One',
            'pred_home_goals' => 2,
            'pred_away_goals' => 1,
            'correct_result' => true,
            'correct_home_goals' => true,
            'correct_away_goals' => false,
            'correct_goal_difference' => true,
            'correct_total_goals' => false,
            'points_total' => 7,
        ],
        [
            'rank' => 2,
            'profile_id' => '22222222-2222-4222-8222-222222222222',
            'display_name' => 'Player Two',
            'pred_home_goals' => 0,
            'pred_away_goals' => 0,
            'correct_result' => false,
            'correct_home_goals' => false,
            'correct_away_goals' => false,
            'correct_goal_difference' => false,
            'correct_total_goals' => false,
            'points_total' => 0,
        ],
    ];

    setupFixtureLeaderboardService(493, $rows);

    $response = $this->getJson(
        route('fixtures.leaderboard', [
            'flavour' => 'epl',
            'fixture' => 493,
        ])
    );

    $response
        ->assertOk()
        ->assertExactJson([
            'fixture_id' => 493,
            'leaderboard' => $rows,
        ]);
})->with([
    'live during normal time' => ['live_90'],
    'live during extra time' => ['live_et'],
    'finished' => ['finished'],
]);

test('preserves tied ranks and leaderboard ordering', function () {
    setupFixtureLeaderboardContext([
        fixtureLeaderboardTestFixture(493, 'finished'),
    ]);

    $rows = [
        [
            'rank' => 1,
            'profile_id' => '11111111-1111-4111-8111-111111111111',
            'display_name' => 'Alice',
            'pred_home_goals' => 2,
            'pred_away_goals' => 0,
            'points_total' => 10,
        ],
        [
            'rank' => 1,
            'profile_id' => '22222222-2222-4222-8222-222222222222',
            'display_name' => 'Bob',
            'pred_home_goals' => 2,
            'pred_away_goals' => 1,
            'points_total' => 10,
        ],
        [
            'rank' => 3,
            'profile_id' => '33333333-3333-4333-8333-333333333333',
            'display_name' => 'Charlie',
            'pred_home_goals' => 0,
            'pred_away_goals' => 0,
            'points_total' => 5,
        ],
    ];

    setupFixtureLeaderboardService(493, $rows);

    $response = $this->getJson(
        route('fixtures.leaderboard', [
            'flavour' => 'epl',
            'fixture' => 493,
        ])
    );

    $response
        ->assertOk()
        ->assertJsonPath('leaderboard.0.rank', 1)
        ->assertJsonPath('leaderboard.1.rank', 1)
        ->assertJsonPath('leaderboard.2.rank', 3)
        ->assertJsonPath('leaderboard.0.display_name', 'Alice')
        ->assertJsonPath('leaderboard.1.display_name', 'Bob')
        ->assertJsonPath('leaderboard.2.display_name', 'Charlie');
});

test('returns an empty leaderboard when no scores exist', function () {
    setupFixtureLeaderboardContext([
        fixtureLeaderboardTestFixture(493, 'live_90'),
    ]);

    setupFixtureLeaderboardService(493, []);

    $response = $this->getJson(
        route('fixtures.leaderboard', [
            'flavour' => 'epl',
            'fixture' => 493,
        ])
    );

    $response
        ->assertOk()
        ->assertExactJson([
            'fixture_id' => 493,
            'leaderboard' => [],
        ]);
});

test('rejects fixtures that are not live or finished', function (
    string $status
) {
    setupFixtureLeaderboardContext([
        fixtureLeaderboardTestFixture(493, $status),
    ]);

    preventFixtureLeaderboardServiceCall();

    $response = $this->getJson(
        route('fixtures.leaderboard', [
            'flavour' => 'epl',
            'fixture' => 493,
        ])
    );

    $response->assertNotFound();
})->with([
    'upcoming' => ['upcoming'],
    'postponed' => ['postponed'],
]);

test('rejects fixtures outside the selected flavour', function () {
    setupFixtureLeaderboardContext([
        fixtureLeaderboardTestFixture(492, 'finished'),
        fixtureLeaderboardTestFixture(494, 'live_90'),
    ]);

    preventFixtureLeaderboardServiceCall();

    $response = $this->getJson(
        route('fixtures.leaderboard', [
            'flavour' => 'epl',
            'fixture' => 999,
        ])
    );

    $response->assertNotFound();
});

test('rejects a zero fixture ID', function () {
    /*
     * The numeric route constraint permits "0",
     * but the controller explicitly rejects it.
     */
    $context = Mockery::mock(
        Perfect10RequestContext::class
    );

    $context->shouldNotReceive('fixtures');

    app()->instance(
        Perfect10RequestContext::class,
        $context
    );

    preventFixtureLeaderboardServiceCall();

    $response = $this->getJson(
        route('fixtures.leaderboard', [
            'flavour' => 'epl',
            'fixture' => 0,
        ])
    );

    $response->assertNotFound();
});

test('rejects non-numeric fixture IDs at the route level', function () {
    $response = $this->getJson(
        '/epl/fixtures/not-a-fixture/leaderboard'
    );

    $response->assertNotFound();
});

test('supports fixtures belonging to a per-fixture flavour', function () {
    /*
     * The controller deliberately has no GWK-only restriction.
     * It checks fixture membership through the selected flavour.
     */
    setupFixtureLeaderboardContext([
        fixtureLeaderboardTestFixture(800, 'finished'),
    ]);

    setupFixtureLeaderboardService(800, [
        [
            'rank' => 1,
            'profile_id' => '11111111-1111-4111-8111-111111111111',
            'display_name' => 'World Cup Player',
            'pred_home_goals' => 1,
            'pred_away_goals' => 0,
            'points_total' => 5,
        ],
    ]);

    $response = $this->getJson(
        route('fixtures.leaderboard', [
            'flavour' => 'wc',
            'fixture' => 800,
        ])
    );

    $response
        ->assertOk()
        ->assertJsonPath('fixture_id', 800)
        ->assertJsonPath('leaderboard.0.display_name', 'World Cup Player');
});

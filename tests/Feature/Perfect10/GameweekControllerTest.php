
<?php

use App\Models\Perfect10\Flavour;
use App\Models\Perfect10\Gameweek;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\Perfect10RequestContext;
use App\Services\Perfect10\PredictionContextService;
use App\Services\Perfect10\TeamContextService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();

    if (
        DB::connection()->getDriverName() !== 'sqlite' ||
        DB::connection()->getDatabaseName() !== ':memory:'
    ) {
        throw new RuntimeException(
            'Gameweek controller tests require in-memory SQLite.'
        );
    }

    Schema::create('teams', function (Blueprint $table) {
        $table->id('team_id');
        $table->string('team_name');
        $table->string('color_1')->nullable();
        $table->string('color_2')->nullable();
        $table->string('color_3')->nullable();
    });

    Schema::create('teams_national', function (Blueprint $table) {
        $table->unsignedBigInteger('team_id')->primary();
        $table->string('flag_code')->nullable();
    });

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
 * Configure the selected flavour and request context.
 */
function setupGameweekControllerContext(
    $test,
    string $formatCode = 'GWK'
): array {
    $selected = [
        'flavour_id' => $formatCode === 'GWK' ? 1 : 2,
        'flavour_name' => $formatCode === 'GWK'
            ? 'Premier League'
            : 'World Cup',
        'flavour_code' => $formatCode === 'GWK' ? 'EPL' : 'WC',
        'format_code' => $formatCode,
        'active_campaign_id' => $formatCode === 'GWK' ? 100 : 200,
    ];

    $flavours = collect([$selected]);

    $flavour = new Flavour([
        'flavour_id' => $selected['flavour_id'],
        'flavour_code' => $selected['flavour_code'],
    ]);

    $context = Mockery::mock(
        Perfect10RequestContext::class
    );

    $context
        ->shouldReceive('flavours')
        ->once()
        ->andReturn($flavours);

    $context
        ->shouldReceive('selectedFlavourData')
        ->once()
        ->andReturn($selected);

    if ($formatCode === 'GWK') {
        $context
            ->shouldReceive('flavour')
            ->once()
            ->andReturn($flavour);
    } else {
        $context->shouldNotReceive('flavour');
        $context->shouldNotReceive('gameweeks');
        $context->shouldNotReceive('activeGameweek');
        $context->shouldNotReceive('fixturesForStage');
    }

    app()->instance(
        Perfect10RequestContext::class,
        $context
    );

    return [$flavour, $context];
}

/**
 * Create a gameweek model without requiring a database row.
 */
function makeGameweekControllerTestWeek(
    int $number,
    string $status = 'finished'
): Gameweek {
    return new Gameweek([
        'gameweek_id' => 495 + $number,
        'campaign_id' => 100,
        'stage_id' => 45 + $number,
        'gameweek_number' => $number,
        'status' => $status,
        'prediction_open_at' => '2026-10-18 12:00:00',
        'prediction_close_at' => '2026-10-24 12:00:00',
    ]);
}

/**
 * Configure the collection used by the gameweek selector.
 *
 * @param  list<Gameweek>  $gameweeks
 */
function setupGameweekControllerWeeks(
    Perfect10RequestContext $context,
    array $gameweeks,
    ?Gameweek $activeGameweek
): void {
    $context
        ->shouldReceive('gameweeks')
        ->once()
        ->andReturn(new EloquentCollection($gameweeks));

    $context
        ->shouldReceive('activeGameweek')
        ->once()
        ->andReturn($activeGameweek);
}

/**
 * Configure empty prediction and team data for routing tests.
 *
 * The main controller test separately exercises the real
 * TeamContextService with populated teams.
 */
function setupGameweekControllerEmptyData(
    Flavour $flavour,
    array $fixtures = []
): void {
    $predictionService = Mockery::mock(
        PredictionContextService::class
    );

    $predictionService
        ->shouldReceive('predictionsForFlavour')
        ->once()
        ->with($flavour, null)
        ->andReturn([]);

    app()->instance(
        PredictionContextService::class,
        $predictionService
    );

    $teamService = Mockery::mock(
        TeamContextService::class
    );

    $teamService
        ->shouldReceive('teamsMapForFixtures')
        ->once()
        ->with($fixtures)
        ->andReturn(collect([]));

    app()->instance(
        TeamContextService::class,
        $teamService
    );
}

test('gameweek page returns active gameweek with filtered fixtures predictions and teams', function () {
    [$flavour, $context] = setupGameweekControllerContext($this);

    $gameweek = makeGameweekControllerTestWeek(5, 'active');

    setupGameweekControllerWeeks(
        $context,
        [$gameweek],
        $gameweek
    );

    $fixtures = [
        [
            'fixture_id' => 101,
            'stage_id' => 50,
            'gameweek_id' => 500,
            'kickoff_at' => '2026-10-24 12:30:00',
            'home_team_id' => 1,
            'away_team_id' => 2,
        ],
        [
            'fixture_id' => 102,
            'stage_id' => 50,
            'gameweek_id' => 500,
            'kickoff_at' => '2026-10-24 15:00:00',
            'home_team_id' => 3,
            'away_team_id' => 4,
        ],
    ];

    $context
        ->shouldReceive('fixturesForStage')
        ->once()
        ->with(50)
        ->andReturn($fixtures);

    $predictionService = Mockery::mock(
        PredictionContextService::class
    );

    $predictionService
        ->shouldReceive('predictionsForFlavour')
        ->once()
        ->with($flavour, null)
        ->andReturn([
            101 => [
                'fixture_id' => 101,
                'pred_home_goals' => 2,
                'pred_away_goals' => 1,
            ],
            102 => [
                'fixture_id' => 102,
                'pred_home_goals' => 0,
                'pred_away_goals' => 0,
            ],
            999 => [
                'fixture_id' => 999,
                'pred_home_goals' => 3,
                'pred_away_goals' => 2,
            ],
        ]);

    app()->instance(
        PredictionContextService::class,
        $predictionService
    );

    DB::table('teams')->insert([
        [
            'team_id' => 1,
            'team_name' => 'Home One',
            'color_1' => '#111111',
        ],
        [
            'team_id' => 2,
            'team_name' => 'Away One',
            'color_2' => '#222222',
        ],
        [
            'team_id' => 3,
            'team_name' => 'Home Two',
            'color_1' => '#333333',
        ],
        [
            'team_id' => 4,
            'team_name' => 'Away Two',
            'color_3' => '#444444',
        ],
    ]);

    DB::table('teams_national')->insert([
        'team_id' => 1,
        'flag_code' => 'gb-eng',
    ]);

    app()->instance(
        TeamContextService::class,
        new TeamContextService
    );

    $response = $this->get(
        route('gameweek', ['flavour' => 'EPL'])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->component('perfect10/gameweek')
            ->where('flavour.flavour_code', 'EPL')
            ->where('activeGameweek.gameweek_id', 500)
            ->where('activeGameweek.gameweek_number', 5)
            ->where('activeGameweek.status', 'active')
            ->where('selectedGameweek.gameweek_id', 500)
            ->where('selectedGameweek.gameweek_number', 5)
            ->has('gameweeks', 1)
            ->where('gameweeks.0.gameweek_id', 500)
            ->where('gameweeks.0.gameweek_number', 5)
            ->where('gameweeks.0.status', 'active')
            ->has('fixtures', 2)
            ->where('fixtures.0.fixture_id', 101)
            ->where('fixtures.1.fixture_id', 102)
            ->has('predictionsMap', 2)
            ->where('predictionsMap.101.pred_home_goals', 2)
            ->where('predictionsMap.102.pred_away_goals', 0)
            ->missing('predictionsMap.999')
            ->has('teamsMap', 4)
            ->where('teamsMap.1.team_name', 'Home One')
            ->where('teamsMap.1.flag_code', 'gb-eng')
            ->where('teamsMap.1.color_1', '#111111')
            ->etc();
    });
});

test('per fixture flavour cannot access gameweek page', function () {
    setupGameweekControllerContext($this, 'PFX');

    $response = $this->get(
        route('gameweek', ['flavour' => 'WC'])
    );

    $response->assertNotFound();
});

test('gameweek page handles the absence of an active gameweek', function () {
    [$flavour, $context] = setupGameweekControllerContext($this);

    setupGameweekControllerWeeks(
        $context,
        [],
        null
    );

    $context->shouldNotReceive('fixturesForStage');

    setupGameweekControllerEmptyData($flavour);

    $response = $this->get(
        route('gameweek', ['flavour' => 'EPL'])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->component('perfect10/gameweek')
            ->where('activeGameweek', null)
            ->where('selectedGameweek', null)
            ->has('gameweeks', 0)
            ->has('fixtures', 0)
            ->has('predictionsMap', 0)
            ->has('teamsMap', 0)
            ->etc();
    });
});

test('historical gameweek can be selected without changing the active gameweek', function () {
    [$flavour, $context] = setupGameweekControllerContext($this);

    $historical = makeGameweekControllerTestWeek(4);
    $active = makeGameweekControllerTestWeek(5, 'active');
    $future = makeGameweekControllerTestWeek(6, 'upcoming');

    setupGameweekControllerWeeks(
        $context,
        [$historical, $active, $future],
        $active
    );

    $fixtures = [
        [
            'fixture_id' => 401,
            'stage_id' => 49,
            'gameweek_id' => 499,
            'kickoff_at' => '2026-10-17 12:30:00',
            'home_team_id' => 1,
            'away_team_id' => 2,
        ],
    ];

    $context
        ->shouldReceive('fixturesForStage')
        ->once()
        ->with(49)
        ->andReturn($fixtures);

    setupGameweekControllerEmptyData($flavour, $fixtures);

    $response = $this->get(
        route('gameweek.show', [
            'flavour' => 'EPL',
            'week' => 4,
        ])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->component('perfect10/gameweek')
            ->where('activeGameweek.gameweek_number', 5)
            ->where('selectedGameweek.gameweek_number', 4)
            ->where('selectedGameweek.status', 'finished')
            ->has('gameweeks', 3)
            ->where('gameweeks.0.gameweek_number', 4)
            ->where('gameweeks.1.gameweek_number', 5)
            ->where('gameweeks.2.gameweek_number', 6)
            ->has('fixtures', 1)
            ->where('fixtures.0.fixture_id', 401)
            ->has('predictionsMap', 0)
            ->etc();
    });
});

test('future gameweek can be selected without changing the active gameweek', function () {
    [$flavour, $context] = setupGameweekControllerContext($this);

    $active = makeGameweekControllerTestWeek(5, 'active');
    $future = makeGameweekControllerTestWeek(6, 'upcoming');

    setupGameweekControllerWeeks(
        $context,
        [$active, $future],
        $active
    );

    $fixtures = [
        [
            'fixture_id' => 601,
            'stage_id' => 51,
            'gameweek_id' => 501,
            'kickoff_at' => '2026-11-07 15:00:00',
            'home_team_id' => 1,
            'away_team_id' => 2,
        ],
    ];

    $context
        ->shouldReceive('fixturesForStage')
        ->once()
        ->with(51)
        ->andReturn($fixtures);

    setupGameweekControllerEmptyData($flavour, $fixtures);

    $response = $this->get(
        route('gameweek.show', [
            'flavour' => 'EPL',
            'week' => 6,
        ])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->component('perfect10/gameweek')
            ->where('activeGameweek.gameweek_number', 5)
            ->where('selectedGameweek.gameweek_number', 6)
            ->where('selectedGameweek.status', 'upcoming')
            ->has('fixtures', 1)
            ->where('fixtures.0.fixture_id', 601)
            ->has('predictionsMap', 0)
            ->etc();
    });
});

test('a specific gameweek remains accessible when none is active', function () {
    [$flavour, $context] = setupGameweekControllerContext($this);

    $historical = makeGameweekControllerTestWeek(4);

    setupGameweekControllerWeeks(
        $context,
        [$historical],
        null
    );

    $context
        ->shouldReceive('fixturesForStage')
        ->once()
        ->with(49)
        ->andReturn([]);

    setupGameweekControllerEmptyData($flavour);

    $response = $this->get(
        route('gameweek.show', [
            'flavour' => 'EPL',
            'week' => 4,
        ])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->component('perfect10/gameweek')
            ->where('activeGameweek', null)
            ->where('selectedGameweek.gameweek_number', 4)
            ->has('gameweeks', 1)
            ->has('fixtures', 0)
            ->etc();
    });
});

test('a gameweek outside the selected campaign is not accessible', function () {
    [, $context] = setupGameweekControllerContext($this);

    $active = makeGameweekControllerTestWeek(5, 'active');

    setupGameweekControllerWeeks(
        $context,
        [$active],
        $active
    );

    $context->shouldNotReceive('fixturesForStage');

    $response = $this->get(
        route('gameweek.show', [
            'flavour' => 'EPL',
            'week' => 12,
        ])
    );

    $response->assertNotFound();
});

test('zero gameweek numbers are rejected', function () {
    [, $context] = setupGameweekControllerContext($this);

    setupGameweekControllerWeeks(
        $context,
        [],
        null
    );

    $context->shouldNotReceive('fixturesForStage');

    $response = $this->get(
        route('gameweek.show', [
            'flavour' => 'EPL',
            'week' => 0,
        ])
    );

    $response->assertNotFound();
});

test('non numeric gameweek numbers are rejected by the route', function () {
    $response = $this->get(
        '/EPL/gameweek/not-a-week'
    );

    $response->assertNotFound();
});

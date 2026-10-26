<?php

use App\Models\Perfect10\Flavour;
use App\Models\Perfect10\Gameweek;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\Perfect10RequestContext;
use App\Services\Perfect10\PredictionContextService;
use App\Services\Perfect10\TeamContextService;
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
        $context->shouldNotReceive('activeGameweek');
        $context->shouldNotReceive('fixturesForStage');
    }

    app()->instance(
        Perfect10RequestContext::class,
        $context
    );

    return [$flavour, $context];
}

test('gameweek page returns active gameweek with filtered fixtures predictions and teams', function () {
    [$flavour, $context] = setupGameweekControllerContext($this);

    $gameweek = new Gameweek([
        'gameweek_id' => 500,
        'campaign_id' => 100,
        'stage_id' => 50,
        'gameweek_number' => 5,
        'status' => 'active',
        'prediction_open_at' => '2026-10-18 12:00:00',
        'prediction_close_at' => '2026-10-24 12:00:00',
    ]);

    $context
        ->shouldReceive('activeGameweek')
        ->once()
        ->andReturn($gameweek);

    /*
     * The active gameweek belongs to Stage 50.
     *
     * Perfect10RequestContext now groups all fixtures by stage_id,
     * including gameweek fixtures.
     */
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

    $context
        ->shouldReceive('activeGameweek')
        ->once()
        ->andReturn(null);

    $context->shouldNotReceive('fixturesForStage');

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

    $response = $this->get(
        route('gameweek', ['flavour' => 'EPL'])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->component('perfect10/gameweek')
            ->where('activeGameweek', null)
            ->has('fixtures', 0)
            ->has('predictionsMap', 0)
            ->has('teamsMap', 0)
            ->etc();
    });
});

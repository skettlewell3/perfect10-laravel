<?php

use App\Models\Perfect10\Campaign;
use App\Models\Perfect10\Flavour;
use App\Models\Perfect10\Gameweek;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\FixtureContextService;
use App\Services\Perfect10\GameweekContextService;
use App\Services\Perfect10\Perfect10RequestContext;
use App\Services\Perfect10\PredictionContextService;
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

    $this->app->instance(
        CurrentProfileService::class,
        $currentProfile
    );
});

/**
 * Configure the selected flavour and campaign for a test.
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

    $campaign = new Campaign([
        'campaign_id' => $selected['active_campaign_id'],
        'competition_id' => $formatCode === 'GWK' ? 10 : 20,
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

        $context
            ->shouldReceive('campaign')
            ->once()
            ->andReturn($campaign);
    } else {
        $context->shouldNotReceive('flavour');
        $context->shouldNotReceive('campaign');
    }

    app()->instance(
        Perfect10RequestContext::class,
        $context
    );

    return [$flavour, $campaign];
}

test('gameweek page returns active gameweek with filtered fixtures predictions and teams', function () {
    [$flavour, $campaign] = setupGameweekControllerContext($this);

    $gameweek = new Gameweek([
        'gameweek_id' => 500,
        'campaign_id' => 100,
        'stage_id' => 50,
        'gameweek_number' => 5,
        'status' => 'active',
        'prediction_open_at' => '2026-10-18 12:00:00',
        'prediction_close_at' => '2026-10-24 12:00:00',
    ]);

    $gameweeks = new EloquentCollection([$gameweek]);

    $gameweekService = Mockery::mock(
        GameweekContextService::class
    );

    $gameweekService
        ->shouldReceive('gameweeksForCampaign')
        ->once()
        ->with($campaign)
        ->andReturn($gameweeks);

    $gameweekService
        ->shouldReceive('activeGameweek')
        ->once()
        ->with($gameweeks)
        ->andReturn($gameweek);

    $this->app->instance(
        GameweekContextService::class,
        $gameweekService
    );

    $fixtures = [
        [
            'fixture_id' => 102,
            'gameweek_id' => 500,
            'kickoff_at' => '2026-10-24 15:00:00',
            'home_team_id' => 3,
            'away_team_id' => 4,
        ],
        [
            'fixture_id' => 999,
            'gameweek_id' => 501,
            'kickoff_at' => '2026-10-23 12:00:00',
            'home_team_id' => 5,
            'away_team_id' => 6,
        ],
        [
            'fixture_id' => 101,
            'gameweek_id' => 500,
            'kickoff_at' => '2026-10-24 12:30:00',
            'home_team_id' => 1,
            'away_team_id' => 2,
        ],
    ];

    $fixtureService = Mockery::mock(
        FixtureContextService::class
    );

    $fixtureService
        ->shouldReceive('fixturesForFlavour')
        ->once()
        ->with($flavour)
        ->andReturn($fixtures);

    $this->app->instance(
        FixtureContextService::class,
        $fixtureService
    );

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

    $this->app->instance(
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

    $gameweekService = Mockery::mock(
        GameweekContextService::class
    );

    $gameweekService->shouldNotReceive(
        'gameweeksForCampaign'
    );

    $this->app->instance(
        GameweekContextService::class,
        $gameweekService
    );

    $response = $this->get(
        route('gameweek', ['flavour' => 'WC'])
    );

    $response->assertNotFound();
});

test('gameweek page handles the absence of an active gameweek', function () {
    [$flavour, $campaign] = setupGameweekControllerContext($this);

    $gameweeks = new EloquentCollection([
        new Gameweek([
            'gameweek_id' => 400,
            'campaign_id' => 100,
            'stage_id' => 40,
            'gameweek_number' => 4,
            'status' => 'finished',
        ]),
    ]);

    $gameweekService = Mockery::mock(
        GameweekContextService::class
    );

    $gameweekService
        ->shouldReceive('gameweeksForCampaign')
        ->once()
        ->with($campaign)
        ->andReturn($gameweeks);

    $gameweekService
        ->shouldReceive('activeGameweek')
        ->once()
        ->with($gameweeks)
        ->andReturn(null);

    $this->app->instance(
        GameweekContextService::class,
        $gameweekService
    );

    $fixtureService = Mockery::mock(
        FixtureContextService::class
    );

    $fixtureService->shouldNotReceive(
        'fixturesForFlavour'
    );

    $this->app->instance(
        FixtureContextService::class,
        $fixtureService
    );

    $predictionService = Mockery::mock(
        PredictionContextService::class
    );

    $predictionService
        ->shouldReceive('predictionsForFlavour')
        ->once()
        ->with($flavour, null)
        ->andReturn([]);

    $this->app->instance(
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

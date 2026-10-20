<?php

use App\Models\Perfect10\Flavour;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\FixtureContextService;
use App\Services\Perfect10\Perfect10RequestContext;
use App\Services\Perfect10\PredictionContextService;
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
            'Fixtures controller tests require in-memory SQLite.'
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
 * Configure the shared request context for a selected flavour.
 */
function setupFixturesControllerContext(
    string $formatCode
): Flavour {
    $isGameweek = $formatCode === 'GWK';

    $selected = [
        'flavour_id' => $isGameweek ? 1 : 2,
        'flavour_name' => $isGameweek
            ? 'Premier League'
            : 'World Cup',
        'flavour_code' => $isGameweek ? 'EPL' : 'WC',
        'is_default' => $isGameweek,
        'competition_id' => $isGameweek ? 10 : 20,
        'competition_name' => $isGameweek
            ? 'Premier League'
            : 'World Cup',
        'competition_code' => $isGameweek ? 'EPL' : 'WCP',
        'format_id' => $isGameweek ? 1 : 2,
        'format_name' => $isGameweek
            ? 'Gameweek'
            : 'Per Fixture',
        'format_code' => $formatCode,
        'active_campaign_id' => $isGameweek ? 100 : 200,
        'active_campaign_code' => $isGameweek
            ? 'EPL2627'
            : 'WCP2026',
        'active_campaign_label' => $isGameweek
            ? 'Premier League 26/27'
            : 'World Cup 2026',
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

    $context
        ->shouldReceive('flavour')
        ->once()
        ->andReturn($flavour);

    // Use Laravel's application helper rather than accessing
    // the protected $app property from a standalone function.
    app()->instance(
        Perfect10RequestContext::class,
        $context
    );

    return $flavour;
}

test('fixtures page returns fixture predictions and team information for both formats', function (
    string $flavourCode,
    string $formatCode
) {
    $flavour = setupFixturesControllerContext($formatCode);

    $fixtureRows = [
        [
            'fixture_id' => 101,
            'gameweek_id' => $formatCode === 'GWK' ? 500 : null,
            'kickoff_at' => '2026-10-24 12:30:00',
            'home_team_id' => 1,
            'away_team_id' => 2,
        ],
        [
            'fixture_id' => 102,
            'gameweek_id' => null,
            'kickoff_at' => '2026-10-24 15:00:00',
            'home_team_id' => 3,
            'away_team_id' => 4,
        ],
    ];

    $fixtureService = Mockery::mock(
        FixtureContextService::class
    );

    $fixtureService
        ->shouldReceive('fixturesForFlavour')
        ->once()
        ->with($flavour)
        ->andReturn($fixtureRows);

    $this->app->instance(
        FixtureContextService::class,
        $fixtureService
    );

    $predictions = [
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
    ];

    $predictionService = Mockery::mock(
        PredictionContextService::class
    );

    $predictionService
        ->shouldReceive('predictionsForFlavour')
        ->once()
        ->with($flavour, null)
        ->andReturn($predictions);

    $this->app->instance(
        PredictionContextService::class,
        $predictionService
    );

    DB::table('teams')->insert([
        [
            'team_id' => 1,
            'team_name' => 'Home One',
            'color_1' => '#111111',
            'color_2' => null,
            'color_3' => null,
        ],
        [
            'team_id' => 2,
            'team_name' => 'Away One',
            'color_1' => null,
            'color_2' => '#222222',
            'color_3' => null,
        ],
        [
            'team_id' => 3,
            'team_name' => 'Home Two',
            'color_1' => '#333333',
            'color_2' => null,
            'color_3' => null,
        ],
        [
            'team_id' => 4,
            'team_name' => 'Away Two',
            'color_1' => null,
            'color_2' => null,
            'color_3' => '#444444',
        ],
    ]);

    expect(
        DB::table('teams')
            ->where('team_id', 2)
            ->value('color_2')
    )->toBe('#222222');

    DB::table('teams_national')->insert([
        'team_id' => 1,
        'flag_code' => 'gb-eng',
    ]);

    $response = $this->get(
        route('fixtures', ['flavour' => $flavourCode])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) use (
        $flavourCode,
        $formatCode
    ) {
        $page
            ->component('perfect10/fixtures')
            ->where('flavour.flavour_code', $flavourCode)
            ->where('flavour.format_code', $formatCode)
            ->has('fixtures', 2)
            ->where('fixtures.0.fixture_id', 101)
            ->where('fixtures.1.fixture_id', 102)
            ->has('predictionsMap', 2)
            ->where('predictionsMap.101.pred_home_goals', 2)
            ->where('predictionsMap.102.pred_away_goals', 0)
            ->has('teamsMap', 4)
            ->where('teamsMap.1.team_name', 'Home One')
            ->where('teamsMap.1.flag_code', 'gb-eng')
            ->where('teamsMap.1.color_1', '#111111')
            ->where('teamsMap.2.color_2', '#222222')
            ->where('teamsMap.4.color_3', '#444444')
            ->etc();
    });
})->with([
    'gameweek format' => ['EPL', 'GWK'],
    'per fixture format' => ['WC', 'PFX'],
]);

test('fixtures page handles an empty fixture collection', function () {
    $flavour = setupFixturesControllerContext('GWK');

    $fixtureService = Mockery::mock(
        FixtureContextService::class
    );

    $fixtureService
        ->shouldReceive('fixturesForFlavour')
        ->once()
        ->with($flavour)
        ->andReturn([]);

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
        route('fixtures', ['flavour' => 'EPL'])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->component('perfect10/fixtures')
            ->where('flavour.flavour_code', 'EPL')
            ->has('fixtures', 0)
            ->has('predictionsMap', 0)
            ->has('teamsMap', 0)
            ->etc();
    });
});

test('fixtures page preserves rearranged fixtures without gameweeks', function () {
    $flavour = setupFixturesControllerContext('GWK');

    $fixtureRows = [
        [
            'fixture_id' => 458,
            'gameweek_id' => null,
            'kickoff_at' => '2026-11-12 20:00:00',
            'home_team_id' => null,
            'away_team_id' => null,
        ],
    ];

    $fixtureService = Mockery::mock(
        FixtureContextService::class
    );

    $fixtureService
        ->shouldReceive('fixturesForFlavour')
        ->once()
        ->with($flavour)
        ->andReturn($fixtureRows);

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
        route('fixtures', ['flavour' => 'EPL'])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->component('perfect10/fixtures')
            ->has('fixtures', 1)
            ->where('fixtures.0.fixture_id', 458)
            ->where('fixtures.0.gameweek_id', null)
            ->has('teamsMap', 0)
            ->etc();
    });
});

<?php

use App\Models\Perfect10\Campaign;
use App\Services\Perfect10\Perfect10RequestContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->withoutVite();

    if (
        DB::connection()->getDriverName() !== 'sqlite' ||
        DB::connection()->getDatabaseName() !== ':memory:'
    ) {
        throw new RuntimeException(
            'Fixture live-data controller tests require in-memory SQLite.'
        );
    }

    Schema::create('fixtures', function (Blueprint $table) {
        $table->integer('fixture_id')->primary();
        $table->integer('campaign_id');
        $table->integer('tie_id')->nullable();
        $table->string('fixture_status');
        $table->string('prediction_open_at')->nullable();
        $table->string('prediction_close_at')->nullable();
    });

    Schema::create('results', function (Blueprint $table) {
        $table->integer('fixture_id')->primary();
        $table->integer('ft_home_goals')->nullable();
        $table->integer('ft_away_goals')->nullable();
        $table->integer('final_home_goals')->nullable();
        $table->integer('final_away_goals')->nullable();
        $table->string('result_type')->nullable();
    });

    Schema::create('ties', function (Blueprint $table) {
        $table->integer('tie_id')->primary();
        $table->integer('winner_team_id')->nullable();
        $table->string('resolution_type')->nullable();
    });
});

/**
 * Mock the campaign selected by the request context.
 */
function setupFixtureLiveDataCampaign(int $campaignId = 2): void
{
    $campaign = new Campaign();

    $campaign->setAttribute(
        $campaign->getKeyName(),
        $campaignId
    );

    $context = Mockery::mock(
        Perfect10RequestContext::class
    );

    $context
        ->shouldReceive('campaign')
        ->once()
        ->andReturn($campaign);

    app()->instance(
        Perfect10RequestContext::class,
        $context
    );
}

/**
 * Insert a fixture into the test database.
 */
function insertFixtureLiveDataFixture(
    int $fixtureId,
    int $campaignId = 2,
    string $status = 'live_90',
    ?int $tieId = null
): void {
    DB::table('fixtures')->insert([
        'fixture_id' => $fixtureId,
        'campaign_id' => $campaignId,
        'tie_id' => $tieId,
        'fixture_status' => $status,
        'prediction_open_at' => '2026-10-24 12:30:00',
        'prediction_close_at' => '2026-10-31 12:00:00',
    ]);
}

test('returns current fixture scores and status', function () {
    setupFixtureLiveDataCampaign();

    insertFixtureLiveDataFixture(501);

    DB::table('results')->insert([
        'fixture_id' => 501,
        'ft_home_goals' => 2,
        'ft_away_goals' => 0,
        'final_home_goals' => 2,
        'final_away_goals' => 0,
        'result_type' => null,
    ]);

    $response = $this->getJson(
        route('fixtures.live-data', [
            'flavour' => 'epl',
            'fixture' => 501,
        ])
    );

    $response
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('fixture.fixture_id', 501)
        ->assertJsonPath('fixture.fixture_status', 'live_90')
        ->assertJsonPath('fixture.ft_home_goals', 2)
        ->assertJsonPath('fixture.ft_away_goals', 0)
        ->assertJsonPath('fixture.final_home_goals', 2)
        ->assertJsonPath('fixture.final_away_goals', 0);
});

test('returns an upcoming fixture without a result', function () {
    setupFixtureLiveDataCampaign();

    insertFixtureLiveDataFixture(
        502,
        2,
        'upcoming'
    );

    $response = $this->getJson(
        route('fixtures.live-data', [
            'flavour' => 'epl',
            'fixture' => 502,
        ])
    );

    $response
        ->assertOk()
        ->assertJsonPath('fixture.fixture_id', 502)
        ->assertJsonPath('fixture.fixture_status', 'upcoming')
        ->assertJsonPath('fixture.ft_home_goals', null)
        ->assertJsonPath('fixture.ft_away_goals', null);
});

test('returns tie resolution data when available', function () {
    setupFixtureLiveDataCampaign();

    insertFixtureLiveDataFixture(
        800,
        2,
        'finished',
        10
    );

    DB::table('ties')->insert([
        'tie_id' => 10,
        'winner_team_id' => 25,
        'resolution_type' => 'penalties',
    ]);

    DB::table('results')->insert([
        'fixture_id' => 800,
        'ft_home_goals' => 1,
        'ft_away_goals' => 1,
        'final_home_goals' => 2,
        'final_away_goals' => 2,
        'result_type' => null,
    ]);

    $response = $this->getJson(
        route('fixtures.live-data', [
            'flavour' => 'wc',
            'fixture' => 800,
        ])
    );

    $response
        ->assertOk()
        ->assertJsonPath('fixture.winner_team_id', 25)
        ->assertJsonPath('fixture.resolution_type', 'penalties')
        ->assertJsonPath('fixture.final_home_goals', 2)
        ->assertJsonPath('fixture.final_away_goals', 2);
});

test('rejects fixtures outside the selected campaign', function () {
    setupFixtureLiveDataCampaign(2);

    insertFixtureLiveDataFixture(
        501,
        3
    );

    $response = $this->getJson(
        route('fixtures.live-data', [
            'flavour' => 'epl',
            'fixture' => 501,
        ])
    );

    $response->assertNotFound();
});

test('rejects a fixture that does not exist', function () {
    setupFixtureLiveDataCampaign();

    $response = $this->getJson(
        route('fixtures.live-data', [
            'flavour' => 'epl',
            'fixture' => 999,
        ])
    );

    $response->assertNotFound();
});

test('rejects a zero fixture ID', function () {
    $context = Mockery::mock(
        Perfect10RequestContext::class
    );

    $context->shouldNotReceive('campaign');

    app()->instance(
        Perfect10RequestContext::class,
        $context
    );

    $response = $this->getJson(
        route('fixtures.live-data', [
            'flavour' => 'epl',
            'fixture' => 0,
        ])
    );

    $response->assertNotFound();
});

test('rejects non-numeric fixture IDs', function () {
    $response = $this->getJson(
        '/epl/fixtures/not-a-fixture/data'
    );

    $response->assertNotFound();
});

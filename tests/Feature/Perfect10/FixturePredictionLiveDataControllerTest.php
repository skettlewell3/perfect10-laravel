
<?php

use App\Models\Perfect10\Campaign;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\Perfect10RequestContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const PREDICTION_LIVE_TEST_PROFILE = '11111111-1111-4111-8111-111111111111';
const PREDICTION_LIVE_OTHER_PROFILE = '22222222-2222-4222-8222-222222222222';

beforeEach(function () {
    $this->withoutVite();

    if (
        DB::connection()->getDriverName() !== 'sqlite' ||
        DB::connection()->getDatabaseName() !== ':memory:'
    ) {
        throw new RuntimeException(
            'Prediction live-data controller tests require in-memory SQLite.'
        );
    }

    Schema::create('fixtures', function (Blueprint $table) {
        $table->integer('fixture_id')->primary();
        $table->integer('campaign_id');
    });

    Schema::create('predictions', function (Blueprint $table) {
        $table->integer('prediction_id')->primary();
        $table->integer('fixture_id');
        $table->string('profile_id');
        $table->integer('pred_home_goals');
        $table->integer('pred_away_goals');
        $table->string('created_at');
        $table->string('updated_at');
    });

    Schema::create('prediction_scores', function (Blueprint $table) {
        $table->integer('prediction_id')->primary();
        $table->boolean('correct_result')->nullable();
        $table->boolean('correct_home_goals')->nullable();
        $table->boolean('correct_away_goals')->nullable();
        $table->boolean('correct_goal_difference')->nullable();
        $table->boolean('correct_total_goals')->nullable();
        $table->integer('points_total')->nullable();
        $table->boolean('perfect_10')->nullable();
    });
});

/**
 * Mock the current campaign and profile.
 */
function setupPredictionLiveDataContext(
    int $campaignId = 2,
    ?string $profileId = PREDICTION_LIVE_TEST_PROFILE
): void {
    $campaign = new Campaign;

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

    $currentProfile = Mockery::mock(
        CurrentProfileService::class
    );

    $currentProfile
        ->shouldReceive('profile')
        ->zeroOrMoreTimes()
        ->andReturn(null);

    $currentProfile
        ->shouldReceive('profileId')
        ->once()
        ->andReturn($profileId);

    app()->instance(
        CurrentProfileService::class,
        $currentProfile
    );
}

/**
 * Add a fixture belonging to the specified campaign.
 */
function insertPredictionLiveTestFixture(
    int $fixtureId = 501,
    int $campaignId = 2
): void {
    DB::table('fixtures')->insert([
        'fixture_id' => $fixtureId,
        'campaign_id' => $campaignId,
    ]);
}

/**
 * Add a saved prediction.
 */
function insertPredictionLiveTestPrediction(
    int $predictionId = 1096802,
    int $fixtureId = 501,
    string $profileId = PREDICTION_LIVE_TEST_PROFILE
): void {
    DB::table('predictions')->insert([
        'prediction_id' => $predictionId,
        'fixture_id' => $fixtureId,
        'profile_id' => $profileId,
        'pred_home_goals' => 1,
        'pred_away_goals' => 2,
        'created_at' => '2026-10-31 10:59:09',
        'updated_at' => '2026-10-31 10:59:09',
    ]);
}

/**
 * Add scoring data for a saved prediction.
 */
function insertPredictionLiveTestScore(
    int $predictionId = 1096802,
    int $pointsTotal = 5
): void {
    DB::table('prediction_scores')->insert([
        'prediction_id' => $predictionId,
        'correct_result' => true,
        'correct_home_goals' => false,
        'correct_away_goals' => true,
        'correct_goal_difference' => false,
        'correct_total_goals' => false,
        'points_total' => $pointsTotal,
        'perfect_10' => false,
    ]);
}

test('returns the current users prediction and scoring breakdown', function () {
    setupPredictionLiveDataContext();

    insertPredictionLiveTestFixture();
    insertPredictionLiveTestPrediction();
    insertPredictionLiveTestScore();

    $response = $this->getJson(
        route('fixtures.prediction-data', [
            'flavour' => 'epl',
            'fixture' => 501,
        ])
    );

    $response
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('prediction.fixture_id', 501)
        ->assertJsonPath('prediction.prediction_id', 1096802)
        ->assertJsonPath(
            'prediction.profile_id',
            PREDICTION_LIVE_TEST_PROFILE
        )
        ->assertJsonPath('prediction.pred_home_goals', 1)
        ->assertJsonPath('prediction.pred_away_goals', 2)
        ->assertJsonPath('prediction.points_total', 5);

    /*
     * SQLite returns boolean columns as integers
     * when queried through the query builder.
     */
    expect($response->json('prediction.correct_result'))->toBe(1);
    expect($response->json('prediction.correct_away_goals'))->toBe(1);
    expect($response->json('prediction.correct_home_goals'))->toBe(0);
    expect($response->json('prediction.perfect_10'))->toBe(0);
});

test('returns a prediction without scoring when no score exists', function () {
    setupPredictionLiveDataContext();

    insertPredictionLiveTestFixture();
    insertPredictionLiveTestPrediction();

    $response = $this->getJson(
        route('fixtures.prediction-data', [
            'flavour' => 'epl',
            'fixture' => 501,
        ])
    );

    $response
        ->assertOk()
        ->assertJsonPath('prediction.fixture_id', 501)
        ->assertJsonPath('prediction.pred_home_goals', 1)
        ->assertJsonPath('prediction.pred_away_goals', 2)
        ->assertJsonPath('prediction.points_total', null)
        ->assertJsonPath('prediction.correct_result', null)
        ->assertJsonPath('prediction.perfect_10', null);
});

test('returns null when the current user has not predicted', function () {
    setupPredictionLiveDataContext();

    insertPredictionLiveTestFixture();

    $response = $this->getJson(
        route('fixtures.prediction-data', [
            'flavour' => 'epl',
            'fixture' => 501,
        ])
    );

    $response
        ->assertOk()
        ->assertExactJson([
            'prediction' => null,
        ]);
});

test('returns null when the current user has no profile', function () {
    setupPredictionLiveDataContext(
        profileId: null
    );

    insertPredictionLiveTestFixture();

    $response = $this->getJson(
        route('fixtures.prediction-data', [
            'flavour' => 'epl',
            'fixture' => 501,
        ])
    );

    $response
        ->assertOk()
        ->assertExactJson([
            'prediction' => null,
        ]);
});

test('never returns another users prediction', function () {
    setupPredictionLiveDataContext(
        profileId: PREDICTION_LIVE_TEST_PROFILE
    );

    insertPredictionLiveTestFixture();

    insertPredictionLiveTestPrediction(
        predictionId: 200,
        profileId: PREDICTION_LIVE_OTHER_PROFILE
    );

    insertPredictionLiveTestScore(
        predictionId: 200,
        pointsTotal: 10
    );

    $response = $this->getJson(
        route('fixtures.prediction-data', [
            'flavour' => 'epl',
            'fixture' => 501,
        ])
    );

    $response
        ->assertOk()
        ->assertExactJson([
            'prediction' => null,
        ]);
});

test('selects only the current users prediction when multiple exist', function () {
    setupPredictionLiveDataContext();

    insertPredictionLiveTestFixture();

    insertPredictionLiveTestPrediction(
        predictionId: 100,
        profileId: PREDICTION_LIVE_TEST_PROFILE
    );

    insertPredictionLiveTestPrediction(
        predictionId: 200,
        profileId: PREDICTION_LIVE_OTHER_PROFILE
    );

    insertPredictionLiveTestScore(
        predictionId: 100,
        pointsTotal: 5
    );

    insertPredictionLiveTestScore(
        predictionId: 200,
        pointsTotal: 10
    );

    $response = $this->getJson(
        route('fixtures.prediction-data', [
            'flavour' => 'epl',
            'fixture' => 501,
        ])
    );

    $response
        ->assertOk()
        ->assertJsonPath('prediction.prediction_id', 100)
        ->assertJsonPath(
            'prediction.profile_id',
            PREDICTION_LIVE_TEST_PROFILE
        )
        ->assertJsonPath('prediction.points_total', 5);
});

test('rejects fixtures outside the selected campaign', function () {
    setupPredictionLiveDataContext(
        campaignId: 2
    );

    insertPredictionLiveTestFixture(
        fixtureId: 501,
        campaignId: 3
    );

    insertPredictionLiveTestPrediction();

    $response = $this->getJson(
        route('fixtures.prediction-data', [
            'flavour' => 'epl',
            'fixture' => 501,
        ])
    );

    $response->assertNotFound();
});

test('rejects a fixture that does not exist', function () {
    setupPredictionLiveDataContext();

    $response = $this->getJson(
        route('fixtures.prediction-data', [
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

    $currentProfile = Mockery::mock(
        CurrentProfileService::class
    );

    $currentProfile
        ->shouldReceive('profile')
        ->zeroOrMoreTimes()
        ->andReturn(null);

    $currentProfile->shouldNotReceive('profileId');

    app()->instance(
        CurrentProfileService::class,
        $currentProfile
    );

    $response = $this->getJson(
        route('fixtures.prediction-data', [
            'flavour' => 'epl',
            'fixture' => 0,
        ])
    );

    $response->assertNotFound();
});

test('rejects non-numeric fixture IDs', function () {
    $response = $this->getJson(
        '/epl/fixtures/not-a-fixture/prediction-data'
    );

    $response->assertNotFound();
});

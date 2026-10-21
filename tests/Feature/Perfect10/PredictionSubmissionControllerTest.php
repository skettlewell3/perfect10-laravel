<?php

use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\Perfect10RequestContext;
use App\Services\Perfect10\Perfect10ShellContextService;
use App\Services\Perfect10\PredictionSubmissionService;
use Illuminate\Auth\Middleware\Authenticate;

beforeEach(function () {
    $this->withoutVite();

    // Authentication is tested separately. These tests isolate
    // the controller's Perfect10 profile identity requirements.
    $this->withoutMiddleware(Authenticate::class);

    $shellContext = Mockery::mock(
        Perfect10ShellContextService::class
    );

    $shellContext
        ->shouldReceive('profileTicker')
        ->andReturn([
            'tickerStageLabel' => null,
            'global' => null,
            'flavour' => null,
            'campaign' => null,
            'stage' => null,
        ]);

    $this->app->instance(
        Perfect10ShellContextService::class,
        $shellContext
    );
});

/**
 * Available flavours used in controller tests.
 */
function predictionSubmissionFlavours()
{
    return collect([
        [
            'flavour_id' => 1,
            'flavour_name' => 'Premier League',
            'flavour_code' => 'EPL',
            'format_code' => 'GWK',
            'active_campaign_id' => 100,
        ],
        [
            'flavour_id' => 2,
            'flavour_name' => 'World Cup',
            'flavour_code' => 'WC',
            'format_code' => 'PFX',
            'active_campaign_id' => 200,
        ],
    ]);
}

/**
 * Mock the resolved request context.
 */
function predictionSubmissionMockContext(
    string $flavourCode = 'EPL',
    ?int $campaignId = null,
    bool $emptyFlavours = false
): void {
    $flavours = $emptyFlavours
        ? collect()
        : predictionSubmissionFlavours();

    $selected = predictionSubmissionFlavours()
        ->firstWhere('flavour_code', $flavourCode);

    if ($campaignId !== null) {
        $selected['active_campaign_id'] = $campaignId;
    }

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

    app()->instance(
        Perfect10RequestContext::class,
        $context
    );
}

/**
 * Mock the authenticated Perfect10 profile.
 */
function predictionSubmissionMockProfile(
    ?string $profileId
): void {
    $currentProfile = Mockery::mock(
        CurrentProfileService::class
    );

    $currentProfile
        ->shouldReceive('profileId')
        ->once()
        ->andReturn($profileId);

    $currentProfile
        ->shouldReceive('profile')
        ->andReturn(null);

    app()->instance(
        CurrentProfileService::class,
        $currentProfile
    );
}

/**
 * Build one valid prediction.
 */
function predictionSubmissionPayload(): array
{
    return [
        'predictions' => [
            [
                'fixture_id' => 458,
                'pred_home_goals' => 2,
                'pred_away_goals' => 1,
            ],
        ],
    ];
}

test('submissions delegate the correct campaign and format to the service', function (
    string $flavourCode,
    string $formatCode,
    int $campaignId
) {
    $profileId = '11111111-1111-4111-8111-111111111111';

    predictionSubmissionMockContext($flavourCode);
    predictionSubmissionMockProfile($profileId);

    $payload = predictionSubmissionPayload();

    $submissionService = Mockery::mock(
        PredictionSubmissionService::class
    );

    $submissionService
        ->shouldReceive('submit')
        ->once()
        ->with(
            $profileId,
            $campaignId,
            $formatCode,
            $payload['predictions']
        )
        ->andReturn(1);

    $this->app->instance(
        PredictionSubmissionService::class,
        $submissionService
    );

    $response = $this
        ->from('/'.strtolower($flavourCode).'/fixtures')
        ->post(
            route('predictions.store', [
                'flavour' => strtolower($flavourCode),
            ]),
            $payload
        );

    $response->assertRedirect(
        '/'.strtolower($flavourCode).'/fixtures'
    );

    $response->assertSessionHas(
        'success',
        '1 prediction(s) saved successfully.'
    );
})->with([
    'gameweek format' => ['EPL', 'GWK', 100],
    'per fixture format' => ['WC', 'PFX', 200],
]);

test('submission is forbidden without a Perfect10 profile', function () {
    predictionSubmissionMockProfile(null);

    $submissionService = Mockery::mock(
        PredictionSubmissionService::class
    );

    $submissionService
        ->shouldNotReceive('submit');

    $this->app->instance(
        PredictionSubmissionService::class,
        $submissionService
    );

    $response = $this->post(
        route('predictions.store', ['flavour' => 'epl']),
        predictionSubmissionPayload()
    );

    $response->assertForbidden();
});

test('submission rejects a route flavour that does not match the selected flavour', function () {
    predictionSubmissionMockContext('EPL');

    predictionSubmissionMockProfile(
        '11111111-1111-4111-8111-111111111111'
    );

    $submissionService = Mockery::mock(
        PredictionSubmissionService::class
    );

    $submissionService
        ->shouldNotReceive('submit');

    $this->app->instance(
        PredictionSubmissionService::class,
        $submissionService
    );

    $response = $this->post(
        route('predictions.store', ['flavour' => 'invalid']),
        predictionSubmissionPayload()
    );

    $response->assertNotFound();
});

test('submission rejects unavailable flavours', function () {
    predictionSubmissionMockContext(
        flavourCode: 'EPL',
        emptyFlavours: true
    );

    predictionSubmissionMockProfile(
        '11111111-1111-4111-8111-111111111111'
    );

    $submissionService = Mockery::mock(
        PredictionSubmissionService::class
    );

    $submissionService
        ->shouldNotReceive('submit');

    $this->app->instance(
        PredictionSubmissionService::class,
        $submissionService
    );

    $response = $this->post(
        route('predictions.store', ['flavour' => 'epl']),
        predictionSubmissionPayload()
    );

    $response->assertNotFound();
});

test('submission rejects flavours without an active campaign', function () {
    $flavours = predictionSubmissionFlavours();

    $selected = $flavours->firstWhere('flavour_code', 'EPL');

    $selected['active_campaign_id'] = null;

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

    $this->app->instance(
        Perfect10RequestContext::class,
        $context
    );

    predictionSubmissionMockProfile(
        '11111111-1111-4111-8111-111111111111'
    );

    $submissionService = Mockery::mock(
        PredictionSubmissionService::class
    );

    $submissionService
        ->shouldNotReceive('submit');

    $this->app->instance(
        PredictionSubmissionService::class,
        $submissionService
    );

    $response = $this->post(
        route('predictions.store', ['flavour' => 'epl']),
        predictionSubmissionPayload()
    );

    $response->assertNotFound();
});

test('submission validates invalid prediction payloads', function (
    array $payload,
    string $errorField
) {
    predictionSubmissionMockContext('EPL');

    predictionSubmissionMockProfile(
        '11111111-1111-4111-8111-111111111111'
    );

    $submissionService = Mockery::mock(
        PredictionSubmissionService::class
    );

    $submissionService
        ->shouldNotReceive('submit');

    $this->app->instance(
        PredictionSubmissionService::class,
        $submissionService
    );

    $response = $this
        ->from('/epl/fixtures')
        ->post(
            route('predictions.store', ['flavour' => 'epl']),
            $payload
        );

    $response->assertRedirect('/epl/fixtures');

    $response->assertSessionHasErrors([
        $errorField,
    ]);
})->with([
    'missing predictions' => [
        [],
        'predictions',
    ],
    'empty predictions' => [
        ['predictions' => []],
        'predictions',
    ],
    'missing fixture id' => [
        [
            'predictions' => [
                [
                    'pred_home_goals' => 2,
                    'pred_away_goals' => 1,
                ],
            ],
        ],
        'predictions.0.fixture_id',
    ],
    'duplicate fixture id' => [
        [
            'predictions' => [
                [
                    'fixture_id' => 458,
                    'pred_home_goals' => 2,
                    'pred_away_goals' => 1,
                ],
                [
                    'fixture_id' => 458,
                    'pred_home_goals' => 1,
                    'pred_away_goals' => 0,
                ],
            ],
        ],
        'predictions.0.fixture_id',
    ],
    'negative home goals' => [
        [
            'predictions' => [
                [
                    'fixture_id' => 458,
                    'pred_home_goals' => -1,
                    'pred_away_goals' => 1,
                ],
            ],
        ],
        'predictions.0.pred_home_goals',
    ],
    'away goals above maximum' => [
        [
            'predictions' => [
                [
                    'fixture_id' => 458,
                    'pred_home_goals' => 2,
                    'pred_away_goals' => 10,
                ],
            ],
        ],
        'predictions.0.pred_away_goals',
    ],
]);

test('submission reports zero changes when predictions are unchanged', function () {
    $profileId = '11111111-1111-4111-8111-111111111111';

    predictionSubmissionMockContext('EPL');
    predictionSubmissionMockProfile($profileId);

    $payload = predictionSubmissionPayload();

    $submissionService = Mockery::mock(
        PredictionSubmissionService::class
    );

    $submissionService
        ->shouldReceive('submit')
        ->once()
        ->with(
            $profileId,
            100,
            'GWK',
            $payload['predictions']
        )
        ->andReturn(0);

    $this->app->instance(
        PredictionSubmissionService::class,
        $submissionService
    );

    $response = $this
        ->from('/epl/fixtures')
        ->post(
            route('predictions.store', ['flavour' => 'epl']),
            $payload
        );

    $response->assertRedirect('/epl/fixtures');

    $response->assertSessionHas(
        'success',
        '0 prediction(s) saved successfully.'
    );
});

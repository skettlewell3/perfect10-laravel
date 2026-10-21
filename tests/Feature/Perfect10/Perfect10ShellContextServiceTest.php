<?php

use App\Models\Perfect10\Campaign;
use App\Models\Perfect10\Stage;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\Perfect10RequestContext;
use App\Services\Perfect10\Perfect10ShellContextService;
use App\Services\Perfect10\ProfileTickerContextService;
use App\Services\Perfect10\StageContextService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

test('shell context builds profile ticker for requested flavour', function () {
    $profileId =
        '11111111-1111-4111-8111-111111111111';

    $selectedFlavour = [
        'flavour_id' => 1,
        'flavour_name' => 'Premier League',
        'flavour_code' => 'EPL',
        'format_code' => 'GWK',
        'active_campaign_id' => 100,
    ];

    $campaign = new Campaign([
        'campaign_id' => 100,
        'competition_id' => 10,
    ]);

    $tickerStage = new Stage([
        'stage_id' => 50,
        'campaign_id' => 100,
        'competition_id' => 10,
    ]);

    $stages = new EloquentCollection([
        $tickerStage,
    ]);

    $currentProfile = Mockery::mock(
        CurrentProfileService::class
    );

    $currentProfile
        ->shouldReceive('profileId')
        ->once()
        ->andReturn($profileId);

    $context = Mockery::mock(
        Perfect10RequestContext::class
    );

    $context
        ->shouldReceive('selectedFlavourData')
        ->once()
        ->andReturn($selectedFlavour);

    $context
        ->shouldReceive('campaign')
        ->once()
        ->andReturn($campaign);

    $context
        ->shouldReceive('stages')
        ->once()
        ->andReturn($stages);

    $stageContext = Mockery::mock(
        StageContextService::class
    );

    $stageContext
        ->shouldReceive('tickerStage')
        ->once()
        ->with($stages)
        ->andReturn($tickerStage);

    $profileTicker = Mockery::mock(
        ProfileTickerContextService::class
    );

    $profileTicker
        ->shouldReceive('ticker')
        ->once()
        ->with(
            $profileId,
            1,
            100,
            50
        )
        ->andReturn(collect([
            (object) [
                'scope' => 'global',
                'rank_position' => 1,
                'total_points' => 100,
            ],
            (object) [
                'scope' => 'flavour',
                'rank_position' => 2,
                'total_points' => 80,
            ],
            (object) [
                'scope' => 'campaign',
                'rank_position' => 3,
                'total_points' => 60,
            ],
            (object) [
                'scope' => 'stage',
                'rank_position' => 4,
                'total_points' => 40,
            ],
        ]));

    $service = new Perfect10ShellContextService(
        $currentProfile,
        $context,
        $stageContext,
        $profileTicker
    );

    $result = $service->profileTicker('EPL');

    expect($result['global']->total_points)
        ->toBe(100);

    expect($result['global']->rank_position)
        ->toBe(1);

    expect($result['flavour']->total_points)
        ->toBe(80);

    expect($result['campaign']->total_points)
        ->toBe(60);

    expect($result['stage']->total_points)
        ->toBe(40);
});

test('shell context returns empty ticker when profile is unavailable', function () {
    $currentProfile = Mockery::mock(
        CurrentProfileService::class
    );

    $currentProfile
        ->shouldReceive('profileId')
        ->once()
        ->andReturn(null);

    $context = Mockery::mock(
        Perfect10RequestContext::class
    );

    $context->shouldNotReceive(
        'selectedFlavourData'
    );

    $context->shouldNotReceive(
        'campaign'
    );

    $context->shouldNotReceive(
        'stages'
    );

    $stageContext = Mockery::mock(
        StageContextService::class
    );

    $stageContext->shouldNotReceive(
        'tickerStage'
    );

    $profileTicker = Mockery::mock(
        ProfileTickerContextService::class
    );

    $profileTicker->shouldNotReceive(
        'ticker'
    );

    $service = new Perfect10ShellContextService(
        $currentProfile,
        $context,
        $stageContext,
        $profileTicker
    );

    $result = $service->profileTicker('EPL');

    expect($result)->toBe([
        'tickerStageLabel' => null,
        'global' => null,
        'flavour' => null,
        'campaign' => null,
        'stage' => null,
    ]);
});

test('shell context returns empty ticker when ticker stage is unavailable', function () {
    $profileId =
        '11111111-1111-4111-8111-111111111111';

    $selectedFlavour = [
        'flavour_id' => 1,
        'flavour_name' => 'Premier League',
        'flavour_code' => 'EPL',
        'format_code' => 'GWK',
        'active_campaign_id' => 100,
    ];

    $campaign = new Campaign([
        'campaign_id' => 100,
        'competition_id' => 10,
    ]);

    $stages = new EloquentCollection;

    $currentProfile = Mockery::mock(
        CurrentProfileService::class
    );

    $currentProfile
        ->shouldReceive('profileId')
        ->once()
        ->andReturn($profileId);

    $context = Mockery::mock(
        Perfect10RequestContext::class
    );

    $context
        ->shouldReceive('selectedFlavourData')
        ->once()
        ->andReturn($selectedFlavour);

    $context
        ->shouldReceive('campaign')
        ->once()
        ->andReturn($campaign);

    $context
        ->shouldReceive('stages')
        ->once()
        ->andReturn($stages);

    $stageContext = Mockery::mock(
        StageContextService::class
    );

    $stageContext
        ->shouldReceive('tickerStage')
        ->once()
        ->with($stages)
        ->andReturn(null);

    $profileTicker = Mockery::mock(
        ProfileTickerContextService::class
    );

    $profileTicker->shouldNotReceive(
        'ticker'
    );

    $service = new Perfect10ShellContextService(
        $currentProfile,
        $context,
        $stageContext,
        $profileTicker
    );

    $result = $service->profileTicker('EPL');

    expect($result)->toBe([
        'tickerStageLabel' => null,
        'global' => null,
        'flavour' => null,
        'campaign' => null,
        'stage' => null,
    ]);
});

<?php

use App\Models\Perfect10\Campaign;
use App\Models\Perfect10\Flavour;
use App\Models\Perfect10\Stage;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\FlavourContextService;
use App\Services\Perfect10\Perfect10ShellContextService;
use App\Services\Perfect10\ProfileTickerContextService;
use App\Services\Perfect10\StageContextService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (
        DB::connection()->getDriverName() !== 'sqlite' ||
        DB::connection()->getDatabaseName() !== ':memory:'
    ) {
        throw new RuntimeException(
            'Perfect10 shell context tests require in-memory SQLite.'
        );
    }

    Schema::create('flavours', function (Blueprint $table) {
        $table->id('flavour_id');
        $table->string('flavour_code');
    });

    DB::table('flavours')->insert([
        [
            'flavour_id' => 1,
            'flavour_code' => 'EPL',
        ],
    ]);
});

test('shell context builds profile ticker for requested flavour', function () {
    $profileId =
        '11111111-1111-4111-8111-111111111111';

    $flavours = collect([
        [
            'flavour_id' => 1,
            'flavour_name' => 'Premier League',
            'flavour_code' => 'EPL',
            'format_code' => 'GWK',
            'active_campaign_id' => 100,
        ],
    ]);

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

    $flavourContext = Mockery::mock(
        FlavourContextService::class
    );

    $flavourContext
        ->shouldReceive('availableFlavours')
        ->once()
        ->andReturn($flavours);

    $flavourContext
        ->shouldReceive('selectFlavour')
        ->once()
        ->with($flavours, 'EPL')
        ->andReturn($flavours->first());

    $flavourContext
        ->shouldReceive('activeCampaignFor')
        ->once()
        ->withArgs(
            fn (Flavour $flavour) =>
                $flavour->flavour_id === 1
        )
        ->andReturn($campaign);

    $stageContext = Mockery::mock(
        StageContextService::class
    );

    $stageContext
        ->shouldReceive('stagesForCampaign')
        ->once()
        ->with($campaign)
        ->andReturn($stages);

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
        $flavourContext,
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

    $flavourContext = Mockery::mock(
        FlavourContextService::class
    );

    $stageContext = Mockery::mock(
        StageContextService::class
    );

    $profileTicker = Mockery::mock(
        ProfileTickerContextService::class
    );

    $flavourContext->shouldNotReceive(
        'availableFlavours'
    );

    $stageContext->shouldNotReceive(
        'stagesForCampaign'
    );

    $profileTicker->shouldNotReceive(
        'ticker'
    );

    $service = new Perfect10ShellContextService(
        $currentProfile,
        $flavourContext,
        $stageContext,
        $profileTicker
    );

    $result = $service->profileTicker('EPL');

    expect($result)->toBe([
        'global' => null,
        'flavour' => null,
        'campaign' => null,
        'stage' => null,
    ]);
});

test('shell context returns empty ticker when ticker stage is unavailable', function () {
    $profileId =
        '11111111-1111-4111-8111-111111111111';

    $flavours = collect([
        [
            'flavour_id' => 1,
            'flavour_name' => 'Premier League',
            'flavour_code' => 'EPL',
            'format_code' => 'GWK',
            'active_campaign_id' => 100,
        ],
    ]);

    $campaign = new Campaign([
        'campaign_id' => 100,
        'competition_id' => 10,
    ]);

    $stages = new EloquentCollection();

    $currentProfile = Mockery::mock(
        CurrentProfileService::class
    );

    $currentProfile
        ->shouldReceive('profileId')
        ->once()
        ->andReturn($profileId);

    $flavourContext = Mockery::mock(
        FlavourContextService::class
    );

    $flavourContext
        ->shouldReceive('availableFlavours')
        ->once()
        ->andReturn($flavours);

    $flavourContext
        ->shouldReceive('selectFlavour')
        ->once()
        ->with($flavours, 'EPL')
        ->andReturn($flavours->first());

    $flavourContext
        ->shouldReceive('activeCampaignFor')
        ->once()
        ->andReturn($campaign);

    $stageContext = Mockery::mock(
        StageContextService::class
    );

    $stageContext
        ->shouldReceive('stagesForCampaign')
        ->once()
        ->with($campaign)
        ->andReturn($stages);

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
        $flavourContext,
        $stageContext,
        $profileTicker
    );

    $result = $service->profileTicker('EPL');

    expect($result)->toBe([
        'global' => null,
        'flavour' => null,
        'campaign' => null,
        'stage' => null,
    ]);
});
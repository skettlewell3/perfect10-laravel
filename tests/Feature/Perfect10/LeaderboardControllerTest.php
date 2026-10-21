<?php

use App\Models\Perfect10\Campaign;
use App\Models\Perfect10\Competition;
use App\Models\Perfect10\Flavour;
use App\Models\Perfect10\Gameweek;
use App\Models\Perfect10\Stage;
use App\Services\Perfect10\FlavourContextService;
use App\Services\Perfect10\GameweekContextService;
use App\Services\Perfect10\LeaderboardContextService;
use App\Services\Perfect10\StageContextService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
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
            'Leaderboard controller tests require in-memory SQLite.'
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
        [
            'flavour_id' => 2,
            'flavour_code' => 'WC',
        ],
    ]);
});

/**
 * @return Collection<int, array<string, mixed>>
 */
function leaderboardTestFlavours()
{
    return collect([
        [
            'flavour_id' => 1,
            'flavour_name' => 'Premier League',
            'flavour_code' => 'EPL',
            'is_default' => true,
            'competition_id' => 10,
            'competition_name' => 'Premier League',
            'competition_code' => 'EPL',
            'format_id' => 1,
            'format_name' => 'Gameweek',
            'format_code' => 'GWK',
            'active_campaign_id' => 100,
            'active_campaign_code' => 'EPL2627',
            'active_campaign_label' => 'Premier League 26/27',
        ],
        [
            'flavour_id' => 2,
            'flavour_name' => 'World Cup',
            'flavour_code' => 'WC',
            'is_default' => false,
            'competition_id' => 20,
            'competition_name' => 'World Cup',
            'competition_code' => 'WCP',
            'format_id' => 2,
            'format_name' => 'Per Fixture',
            'format_code' => 'PFX',
            'active_campaign_id' => 200,
            'active_campaign_code' => 'WCP2026',
            'active_campaign_label' => 'World Cup 2026',
        ],
    ]);
}

test('gameweek leaderboard only exposes stages that also belong to gameweeks', function () {
    $flavours = leaderboardTestFlavours();
    $selected = $flavours->firstWhere('flavour_code', 'EPL');

    $competition = new Competition([
        'competition_id' => 10,
    ]);

    $campaign = new Campaign([
        'campaign_id' => 100,
        'competition_id' => 10,
    ]);

    $gameweekOne = new Stage([
        'stage_id' => 10,
        'campaign_id' => 100,
        'competition_id' => 10,
        'stage_name' => 'Gameweek 1',
        'stage_code' => 'GW1',
        'order_index' => 1,
        'is_finished' => true,
        'is_live' => false,
    ]);

    $gameweekTwo = new Stage([
        'stage_id' => 11,
        'campaign_id' => 100,
        'competition_id' => 10,
        'stage_name' => 'Gameweek 2',
        'stage_code' => 'GW2',
        'order_index' => 2,
        'is_finished' => false,
        'is_live' => true,
    ]);

    $rearranged = new Stage([
        'stage_id' => 50,
        'campaign_id' => 100,
        'competition_id' => 10,
        'stage_name' => 'Rearranged Fixtures',
        'stage_code' => 'REAR',
        'order_index' => null,
        'is_finished' => true,
        'is_live' => false,
    ]);

    $stages = new EloquentCollection([
        $gameweekOne,
        $gameweekTwo,
        $rearranged,
    ]);

    $gameweeks = new EloquentCollection([
        new Gameweek([
            'gameweek_id' => 1000,
            'campaign_id' => 100,
            'stage_id' => 10,
            'gameweek_number' => 1,
            'status' => 'finished',
        ]),
        new Gameweek([
            'gameweek_id' => 1001,
            'campaign_id' => 100,
            'stage_id' => 11,
            'gameweek_number' => 2,
            'status' => 'active',
        ]),
    ]);

    $flavourService = Mockery::mock(FlavourContextService::class);

    $flavourService
        ->shouldReceive('availableFlavours')
        ->once()
        ->andReturn($flavours);

    $flavourService
        ->shouldReceive('selectFlavour')
        ->once()
        ->with($flavours, 'EPL')
        ->andReturn($selected);

    $flavourService
        ->shouldReceive('activeFlavourModel')
        ->once()
        ->with(1)
        ->andReturn(new Flavour([
            'flavour_id' => 1,
            'flavour_code' => 'EPL',
        ]));

    $flavourService
        ->shouldReceive('competitionFor')
        ->once()
        ->andReturn($competition);

    $flavourService
        ->shouldReceive('activeCampaignFor')
        ->once()
        ->andReturn($campaign);

    $this->app->instance(
        FlavourContextService::class,
        $flavourService
    );

    $stageService = Mockery::mock(StageContextService::class);

    $stageService
        ->shouldReceive('stagesForCampaign')
        ->once()
        ->with($campaign)
        ->andReturn($stages);

    $stageService
        ->shouldReceive('tickerStage')
        ->once()
        ->with($stages)
        ->andReturn($gameweekTwo);

    $this->app->instance(
        StageContextService::class,
        $stageService
    );

    $gameweekService = Mockery::mock(
        GameweekContextService::class
    );

    $gameweekService
        ->shouldReceive('gameweeksForCampaign')
        ->once()
        ->with($campaign)
        ->andReturn($gameweeks);

    $this->app->instance(
        GameweekContextService::class,
        $gameweekService
    );

    $leaderboardRows = collect([
        [
            'rank' => 1,
            'profile_id' => '11111111-1111-1111-1111-111111111111',
            'display_name' => 'Test User',
            'profile_type' => 'user',
            'predicted' => 10,
            'correct_results' => 5,
            'correct_home_goals' => 2,
            'correct_away_goals' => 2,
            'correct_goal_difference' => 3,
            'correct_total_goals' => 2,
            'perfect_10s' => 1,
            'points_total' => 30,
        ],
    ]);

    $leaderboardService = Mockery::mock(
        LeaderboardContextService::class
    );

    $leaderboardService
        ->shouldReceive('leaderboard')
        ->once()
        ->with(
            1,
            'stage',
            11,
            'global'
        )
        ->andReturn($leaderboardRows);

    $this->app->instance(
        LeaderboardContextService::class,
        $leaderboardService
    );

    $response = $this->get(
        route('leaderboards', [
            'flavour' => 'EPL',
        ])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->component('perfect10/leaderboards')
            ->where('flavour.flavour_code', 'EPL')
            ->where('filters.scopeType', 'stage')
            ->where('filters.scopeId', 11)
            ->where('filters.population', 'global')
            ->has('leaderboardStages', 2)
            ->where('leaderboardStages.0.stage_id', 10)
            ->where('leaderboardStages.1.stage_id', 11)
            ->has('leaderboard', 1)
            ->where('leaderboard.0.display_name', 'Test User')
            ->etc();
    });
});

test('per fixture leaderboard uses visible stages without gameweek filtering', function () {
    $flavours = leaderboardTestFlavours();
    $selected = $flavours->firstWhere('flavour_code', 'WC');

    $competition = new Competition([
        'competition_id' => 20,
    ]);

    $campaign = new Campaign([
        'campaign_id' => 200,
        'competition_id' => 20,
    ]);

    $groupStage = new Stage([
        'stage_id' => 4,
        'campaign_id' => 200,
        'competition_id' => 20,
        'stage_name' => 'Group Stage',
        'stage_code' => 'GROUP',
        'order_index' => 1,
        'is_finished' => true,
        'is_live' => false,
    ]);

    $knockoutStage = new Stage([
        'stage_id' => 9,
        'campaign_id' => 200,
        'competition_id' => 20,
        'stage_name' => 'Round of 16',
        'stage_code' => 'R16',
        'order_index' => 2,
        'is_finished' => false,
        'is_live' => true,
    ]);

    $stages = new EloquentCollection([
        $groupStage,
        $knockoutStage,
    ]);

    $flavourService = Mockery::mock(FlavourContextService::class);

    $flavourService
        ->shouldReceive('availableFlavours')
        ->once()
        ->andReturn($flavours);

    $flavourService
        ->shouldReceive('selectFlavour')
        ->once()
        ->with($flavours, 'WC')
        ->andReturn($selected);

    $flavourService
        ->shouldReceive('activeFlavourModel')
        ->once()
        ->with(2)
        ->andReturn(new Flavour([
            'flavour_id' => 2,
            'flavour_code' => 'WC',
        ]));

    $flavourService
        ->shouldReceive('competitionFor')
        ->once()
        ->andReturn($competition);

    $flavourService
        ->shouldReceive('activeCampaignFor')
        ->once()
        ->andReturn($campaign);

    $this->app->instance(
        FlavourContextService::class,
        $flavourService
    );

    $stageService = Mockery::mock(StageContextService::class);

    $stageService
        ->shouldReceive('stagesForCampaign')
        ->once()
        ->with($campaign)
        ->andReturn($stages);

    $stageService
        ->shouldReceive('tickerStage')
        ->once()
        ->with($stages)
        ->andReturn($knockoutStage);

    $this->app->instance(
        StageContextService::class,
        $stageService
    );

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

    $leaderboardService = Mockery::mock(
        LeaderboardContextService::class
    );

    $leaderboardService
        ->shouldReceive('leaderboard')
        ->once()
        ->with(
            2,
            'stage',
            9,
            'global'
        )
        ->andReturn(collect());

    $this->app->instance(
        LeaderboardContextService::class,
        $leaderboardService
    );

    $response = $this->get(
        route('leaderboards', [
            'flavour' => 'WC',
        ])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->component('perfect10/leaderboards')
            ->where('flavour.flavour_code', 'WC')
            ->where('filters.scopeType', 'stage')
            ->where('filters.scopeId', 9)
            ->has('leaderboardStages', 2)
            ->where('leaderboardStages.0.stage_id', 4)
            ->where('leaderboardStages.1.stage_id', 9)
            ->has('leaderboard', 0)
            ->etc();
    });
});

test('leaderboard accepts campaign scope and population filters', function () {
    $flavours = leaderboardTestFlavours();
    $selected = $flavours->firstWhere('flavour_code', 'EPL');

    $competition = new Competition([
        'competition_id' => 10,
    ]);

    $campaign = new Campaign([
        'campaign_id' => 100,
        'competition_id' => 10,
    ]);

    $stage = new Stage([
        'stage_id' => 14,
        'campaign_id' => 100,
        'competition_id' => 10,
        'stage_name' => 'Gameweek 5',
        'stage_code' => 'GW5',
        'order_index' => 5,
        'is_finished' => true,
        'is_live' => false,
    ]);

    $stages = new EloquentCollection([$stage]);

    $gameweeks = new EloquentCollection([
        new Gameweek([
            'gameweek_id' => 500,
            'campaign_id' => 100,
            'stage_id' => 14,
            'gameweek_number' => 5,
            'status' => 'finished',
        ]),
    ]);

    $flavourService = Mockery::mock(FlavourContextService::class);

    $flavourService
        ->shouldReceive('availableFlavours')
        ->once()
        ->andReturn($flavours);

    $flavourService
        ->shouldReceive('selectFlavour')
        ->once()
        ->with($flavours, 'EPL')
        ->andReturn($selected);

    $flavourService
        ->shouldReceive('activeFlavourModel')
        ->once()
        ->with(1)
        ->andReturn(new Flavour([
            'flavour_id' => 1,
            'flavour_code' => 'EPL',
        ]));

    $flavourService
        ->shouldReceive('competitionFor')
        ->once()
        ->andReturn($competition);

    $flavourService
        ->shouldReceive('activeCampaignFor')
        ->once()
        ->andReturn($campaign);

    $this->app->instance(
        FlavourContextService::class,
        $flavourService
    );

    $stageService = Mockery::mock(StageContextService::class);

    $stageService
        ->shouldReceive('stagesForCampaign')
        ->once()
        ->with($campaign)
        ->andReturn($stages);

    $stageService
        ->shouldReceive('tickerStage')
        ->once()
        ->with($stages)
        ->andReturn($stage);

    $this->app->instance(
        StageContextService::class,
        $stageService
    );

    $gameweekService = Mockery::mock(
        GameweekContextService::class
    );

    $gameweekService
        ->shouldReceive('gameweeksForCampaign')
        ->once()
        ->with($campaign)
        ->andReturn($gameweeks);

    $this->app->instance(
        GameweekContextService::class,
        $gameweekService
    );

    $leaderboardService = Mockery::mock(
        LeaderboardContextService::class
    );

    $leaderboardService
        ->shouldReceive('leaderboard')
        ->once()
        ->with(
            1,
            'campaign',
            100,
            'clubs'
        )
        ->andReturn(collect());

    $this->app->instance(
        LeaderboardContextService::class,
        $leaderboardService
    );

    $response = $this->get(
        route('leaderboards', [
            'flavour' => 'EPL',
            'scope' => 'campaign',
            'population' => 'clubs',
        ])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->component('perfect10/leaderboards')
            ->where('filters.scopeType', 'campaign')
            ->where('filters.scopeId', 100)
            ->where('filters.population', 'clubs')
            ->etc();
    });
});

test('invalid leaderboard filters fall back to safe defaults', function () {
    $flavours = leaderboardTestFlavours();
    $selected = $flavours->firstWhere('flavour_code', 'EPL');

    $competition = new Competition([
        'competition_id' => 10,
    ]);

    $campaign = new Campaign([
        'campaign_id' => 100,
        'competition_id' => 10,
    ]);

    $stage = new Stage([
        'stage_id' => 14,
        'campaign_id' => 100,
        'competition_id' => 10,
        'stage_name' => 'Gameweek 5',
        'stage_code' => 'GW5',
        'order_index' => 5,
        'is_finished' => true,
        'is_live' => false,
    ]);

    $stages = new EloquentCollection([$stage]);

    $gameweeks = new EloquentCollection([
        new Gameweek([
            'gameweek_id' => 500,
            'campaign_id' => 100,
            'stage_id' => 14,
            'gameweek_number' => 5,
            'status' => 'finished',
        ]),
    ]);

    $flavourService = Mockery::mock(FlavourContextService::class);

    $flavourService
        ->shouldReceive('availableFlavours')
        ->once()
        ->andReturn($flavours);

    $flavourService
        ->shouldReceive('selectFlavour')
        ->once()
        ->with($flavours, 'EPL')
        ->andReturn($selected);

    $flavourService
        ->shouldReceive('activeFlavourModel')
        ->once()
        ->with(1)
        ->andReturn(new Flavour([
            'flavour_id' => 1,
            'flavour_code' => 'EPL',
        ]));

    $flavourService
        ->shouldReceive('competitionFor')
        ->once()
        ->andReturn($competition);

    $flavourService
        ->shouldReceive('activeCampaignFor')
        ->once()
        ->andReturn($campaign);

    $this->app->instance(
        FlavourContextService::class,
        $flavourService
    );

    $stageService = Mockery::mock(StageContextService::class);

    $stageService
        ->shouldReceive('stagesForCampaign')
        ->once()
        ->with($campaign)
        ->andReturn($stages);

    $stageService
        ->shouldReceive('tickerStage')
        ->once()
        ->with($stages)
        ->andReturn($stage);

    $this->app->instance(
        StageContextService::class,
        $stageService
    );

    $gameweekService = Mockery::mock(
        GameweekContextService::class
    );

    $gameweekService
        ->shouldReceive('gameweeksForCampaign')
        ->once()
        ->with($campaign)
        ->andReturn($gameweeks);

    $this->app->instance(
        GameweekContextService::class,
        $gameweekService
    );

    $leaderboardService = Mockery::mock(
        LeaderboardContextService::class
    );

    $leaderboardService
        ->shouldReceive('leaderboard')
        ->once()
        ->with(
            1,
            'stage',
            14,
            'global'
        )
        ->andReturn(collect());

    $this->app->instance(
        LeaderboardContextService::class,
        $leaderboardService
    );

    $response = $this->get(
        route('leaderboards', [
            'flavour' => 'EPL',
            'scope' => 'nonsense',
            'population' => 'nonsense',
            'stage' => 999,
        ])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page
            ->where('filters.scopeType', 'stage')
            ->where('filters.scopeId', 14)
            ->where('filters.population', 'global')
            ->etc();
    });
});

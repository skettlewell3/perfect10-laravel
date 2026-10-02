
<?php

use App\Models\Perfect10\Campaign;
use App\Models\Perfect10\Competition;
use App\Models\Perfect10\Gameweek;
use App\Models\Perfect10\Stage;
use App\Services\Perfect10\FixtureContextService;
use App\Services\Perfect10\FlavourContextService;
use App\Services\Perfect10\GameweekContextService;
use App\Services\Perfect10\StageContextService;
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
            'Dashboard controller tests require in-memory SQLite.'
        );
    }

    // The controller only needs this table to retrieve
    // the selected flavour model by its ID.
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

test('dashboard resolves the appropriate context for each prediction format', function (
    string $requestedCode,
    string $expectedFormat
) {
    $flavours = collect([
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

    $selected = $flavours->firstWhere(
        'flavour_code',
        trim($requestedCode)
    );

    $competition = new Competition([
        'competition_id' => $selected['competition_id'],
    ]);

    $campaign = new Campaign([
        'campaign_id' => $selected['active_campaign_id'],
        'competition_id' => $selected['competition_id'],
    ]);

    $activeStage = new Stage([
        'stage_id' => 50,
        'campaign_id' => $selected['active_campaign_id'],
        'competition_id' => $selected['competition_id'],
        'is_active' => true,
    ]);

    $stages = new EloquentCollection([$activeStage]);

    // Mock the flavour context service.
    $flavourService = Mockery::mock(FlavourContextService::class);

    $flavourService
        ->shouldReceive('availableFlavours')
        ->once()
        ->andReturn($flavours);

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

    // Mock the universal stage context.
    $stageService = Mockery::mock(StageContextService::class);

    $stageService
        ->shouldReceive('stagesForCampaign')
        ->once()
        ->with($campaign)
        ->andReturn($stages);

    $stageService
        ->shouldReceive('activeStage')
        ->once()
        ->with($stages)
        ->andReturn($activeStage);

    $stageService
        ->shouldReceive('tickerStage')
        ->once()
        ->with($stages)
        ->andReturn($activeStage);

    $this->app->instance(
        StageContextService::class,
        $stageService
    );

    // GWK must use the gameweek service; PFX must not.
    $gameweekService = Mockery::mock(
        GameweekContextService::class
    );

    if ($expectedFormat === 'GWK') {
        $activeGameweek = new Gameweek([
            'gameweek_id' => 500,
            'campaign_id' => $selected['active_campaign_id'],
            'stage_id' => 50,
            'gameweek_number' => 1,
            'status' => 'active',
        ]);

        $gameweeks = new EloquentCollection([$activeGameweek]);

        $gameweekService
            ->shouldReceive('gameweeksForCampaign')
            ->once()
            ->with($campaign)
            ->andReturn($gameweeks);

        $gameweekService
            ->shouldReceive('validatedActiveGameweek')
            ->once()
            ->with($gameweeks, $activeStage)
            ->andReturn($activeGameweek);

        $gameweekService
            ->shouldReceive('nextGameweek')
            ->once()
            ->with($gameweeks)
            ->andReturn(null);
    } else {
        $gameweekService->shouldNotReceive(
            'gameweeksForCampaign'
        );

        $gameweekService->shouldNotReceive(
            'validatedActiveGameweek'
        );

        $gameweekService->shouldNotReceive(
            'nextGameweek'
        );
    }

    $this->app->instance(
        GameweekContextService::class,
        $gameweekService
    );

    // Mock the fixture context service.
    $fixtureService = Mockery::mock(
        FixtureContextService::class
    );

    $fixtureService
        ->shouldReceive('fixturesForFlavour')
        ->once()
        ->andReturn([]);

    $this->app->instance(
        FixtureContextService::class,
        $fixtureService
    );

    // Exercise the real dashboard route and controller.
    $response = $this->get(
        route('dashboard', ['flavour' => $requestedCode])
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) use (
        $requestedCode,
        $expectedFormat
    ) {
        $page
            ->component('perfect10/dashboard')
            ->where('flavour.flavour_code', trim($requestedCode))
            ->where('flavour.format_code', $expectedFormat)
            ->has('fixtures', 0)
            ->has('stageContext.stages', 1)
            ->where('stageContext.activeStage.stage_id', 50)
            ->where('stageContext.tickerStage.stage_id', 50);

        if ($expectedFormat === 'GWK') {
            $page
                ->has('gameweekContext.gameweeks', 1)
                ->where(
                    'gameweekContext.activeGameweek.gameweek_id',
                    500
                )
                ->where('gameweekContext.nextGameweek', null);
        } else {
            $page->where('gameweekContext', null);
        }

        $page->etc();
    });
})->with([
    'gameweek format' => ['EPL', 'GWK'],
    'per-fixture format' => ['WC', 'PFX'],
    'per-fixture format with trailing whitespace' => ['WC ', 'PFX'],
]);

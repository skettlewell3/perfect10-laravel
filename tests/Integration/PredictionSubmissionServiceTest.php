<?php

namespace Tests\Integration\Perfect10;

use App\Services\Perfect10\PredictionSubmissionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PredictionSubmissionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PredictionSubmissionService $service;

    private string $profileId;

    private int $campaignId;

    private array $fixtureIds = [];

    /**
     * Ensure a test transaction can only open against the isolated local DB.
     */
    protected function connectionsToTransact(): array
    {
        if (
            config('database.default') !== 'pgsql' ||
            config('database.connections.pgsql.database') !== 'perfect10_testing' ||
            config('database.connections.pgsql.host') !== '127.0.0.1' ||
            (int) config('database.connections.pgsql.port') !== 5432
        ) {
            throw new RuntimeException(
                'Prediction integration tests must use the local perfect10_testing database.'
            );
        }

        return ['pgsql'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (
            DB::getDefaultConnection() !== 'pgsql' ||
            DB::connection()->getDatabaseName() !== 'perfect10_testing' ||
            DB::connection()->getConfig('host') !== '127.0.0.1' ||
            (int) DB::connection()->getConfig('port') !== 5432
        ) {
            throw new RuntimeException(
                'Incorrect prediction integration test database.'
            );
        }

        $this->service = app(PredictionSubmissionService::class);
        $this->createTestData();
    }

    /**
     * All inserted rows are rolled back by DatabaseTransactions.
     */
    private function createTestData(): void
    {
        $now = CarbonImmutable::now('UTC');
        $this->profileId = (string) Str::uuid();

        DB::table('profiles')->insert([
            'profile_id' => $this->profileId,
            'profile_type' => 'user',
            'display_name' => 'Test User',
        ]);

        $competitionId = (int) DB::table('competitions')->insertGetId([
            'competition_name' => 'Prediction Test League',
            'competition_code' => 'TST',
        ], 'competition_id');

        $this->campaignId = (int) DB::table('campaigns')->insertGetId([
            'competition_id' => $competitionId,
            'label' => 'Integration Test Season',
            'code' => 'TEST-2026',
            'is_active' => true,
        ], 'campaign_id');

        $stageId = (int) DB::table('stages')->insertGetId([
            'campaign_id' => $this->campaignId,
            'competition_id' => $competitionId,
            'stage_name' => 'Test Gameweek 1',
            'stage_code' => 'TEST-GW1',
            'stage_type' => 'league',
            'order_index' => 1,
            'is_active' => true,
            'is_live' => false,
            'is_finished' => false,
        ], 'stage_id');

        $countryId = (int) DB::table('countries')->insertGetId([
            'name' => 'Test Country',
            'iso2' => 'GB',
        ], 'country_id');

        $cityId = (int) DB::table('cities')->insertGetId([
            'city_name' => 'Test City',
            'timezone' => 'Europe/London',
            'country_id' => $countryId,
        ], 'city_id');

        $venueId = (int) DB::table('venues')->insertGetId([
            'venue_name' => 'Test Stadium',
            'city_id' => $cityId,
        ], 'venue_id');

        $gameweekId = (int) DB::table('gameweeks')->insertGetId([
            'campaign_id' => $this->campaignId,
            'stage_id' => $stageId,
            'gameweek_number' => 1,
            'status' => 'active',
            'prediction_open_at' => $now->subDay(),
            'prediction_close_at' => $now->addDay(),
        ], 'gameweek_id');

        for ($i = 0; $i < 10; $i++) {
            $fixtureId = (int) DB::table('fixtures')->insertGetId([
                'campaign_id' => $this->campaignId,
                'stage_id' => $stageId,
                'venue_id' => $venueId,
                'home_team_id' => null,
                'away_team_id' => null,
                'kickoff_at' => $now->addDays(3)->addHours($i),
                'fixture_status' => 'upcoming',
                'prediction_open_at' => $now->subDay(),
                'prediction_close_at' => $now->addDay(),
            ], 'fixture_id');

            $this->fixtureIds[] = $fixtureId;
        }

        DB::table('gameweeks')
            ->where('gameweek_id', $gameweekId)
            ->update([
                'status' => 'active',
                'prediction_open_at' => $now->subDay(),
                'prediction_close_at' => $now->addDay(),
            ]);

        $this->assertCount(10, $this->fixtureIds);
    }

    private function predictionRows(array $fixtureIds): array
    {
        return array_map(
            fn (int $fixtureId): array => [
                'fixture_id' => $fixtureId,
                'pred_home_goals' => 2,
                'pred_away_goals' => 1,
            ],
            $fixtureIds,
        );
    }

    public function test_complete_initial_gameweek_submission(): void
    {
        $rows = $this->predictionRows($this->fixtureIds);

        $saved = $this->service->submit(
            $this->profileId,
            $this->campaignId,
            'GWK',
            $rows,
        );

        $this->assertSame(10, $saved);

        $count = DB::table('predictions')
            ->where('profile_id', $this->profileId)
            ->whereIn('fixture_id', $this->fixtureIds)
            ->count();

        $this->assertSame(10, $count);
    }

    public function test_unchanged_predictions_are_not_updated(): void
    {
        $rows = $this->predictionRows($this->fixtureIds);

        $initialCount = $this->service->submit(
            $this->profileId,
            $this->campaignId,
            'GWK',
            $rows,
        );

        $this->assertSame(10, $initialCount);

        $fixtureId = $this->fixtureIds[0];

        $originalUpdatedAt = DB::table('predictions')
            ->where('profile_id', $this->profileId)
            ->where('fixture_id', $fixtureId)
            ->value('updated_at');

        $unchangedCount = $this->service->submit(
            $this->profileId,
            $this->campaignId,
            'GWK',
            [[
                'fixture_id' => $fixtureId,
                'pred_home_goals' => 2,
                'pred_away_goals' => 1,
            ]],
        );

        $this->assertSame(0, $unchangedCount);

        $this->assertEquals(
            $originalUpdatedAt,
            DB::table('predictions')
                ->where('profile_id', $this->profileId)
                ->where('fixture_id', $fixtureId)
                ->value('updated_at')
        );
    }

    public function test_incomplete_initial_gameweek_submission_is_rejected(): void
    {
        $rows = $this->predictionRows(
            array_slice($this->fixtureIds, 0, -1)
        );

        try {
            $this->service->submit(
                $this->profileId,
                $this->campaignId,
                'GWK',
                $rows,
            );

            $this->fail('Expected incomplete submission to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'predictions',
                $exception->errors()
            );
        }

        $this->assertSame(
            0,
            DB::table('predictions')
                ->where('profile_id', $this->profileId)
                ->whereIn('fixture_id', $this->fixtureIds)
                ->count()
        );
    }

    public function test_existing_complete_gameweek_allows_single_prediction_update(): void
    {
        $this->service->submit(
            $this->profileId,
            $this->campaignId,
            'GWK',
            $this->predictionRows($this->fixtureIds),
        );

        $fixtureId = $this->fixtureIds[0];

        $saved = $this->service->submit(
            $this->profileId,
            $this->campaignId,
            'GWK',
            [[
                'fixture_id' => $fixtureId,
                'pred_home_goals' => 3,
                'pred_away_goals' => 0,
            ]],
        );

        $this->assertSame(1, $saved);

        $this->assertDatabaseHas('predictions', [
            'profile_id' => $this->profileId,
            'fixture_id' => $fixtureId,
            'pred_home_goals' => 3,
            'pred_away_goals' => 0,
        ]);

        $this->assertSame(
            10,
            DB::table('predictions')
                ->where('profile_id', $this->profileId)
                ->whereIn('fixture_id', $this->fixtureIds)
                ->count()
        );
    }
}

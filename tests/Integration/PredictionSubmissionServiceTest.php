<?php

namespace Tests\Integration\Perfect10;

use App\Services\Perfect10\PredictionSubmissionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PredictionSubmissionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PredictionSubmissionService $service;

    private string $profileId;

    private int $campaignId;

    private array $fixtureIds;

    protected function setUp(): void
    {
        parent::setUp();

        if (
            ! str_contains(
                strtolower(DB::connection()->getDatabaseName()),
                'test'
            )
        ) {
            throw new RuntimeException(
                'Prediction integration tests require an isolated test database.'
            );
        }

        $this->service = app(PredictionSubmissionService::class);

        $profile = DB::table('profiles')
            ->where('profile_type', 'user')
            ->first();

        $this->assertNotNull($profile, 'A test user profile is required.');

        $this->profileId = $profile->profile_id;

        $gameweek = DB::table('gameweeks as gw')
            ->join('campaigns as c', 'c.campaign_id', '=', 'gw.campaign_id')
            ->join('stages as s', 's.stage_id', '=', 'gw.stage_id')
            ->where('s.stage_type', 'league')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('fixtures as f')
                    ->whereColumn('f.stage_id', 'gw.stage_id')
                    ->whereColumn('f.campaign_id', 'gw.campaign_id');
            })
            ->select('gw.*')
            ->first();

        $this->assertNotNull($gameweek, 'A test gameweek is required.');

        $this->campaignId = (int) $gameweek->campaign_id;

        $this->fixtureIds = DB::table('fixtures')
            ->where('campaign_id', $this->campaignId)
            ->where('stage_id', $gameweek->stage_id)
            ->orderBy('fixture_id')
            ->pluck('fixture_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertGreaterThan(1, count($this->fixtureIds));

        $now = CarbonImmutable::now('UTC');

        DB::table('campaigns')
            ->where('campaign_id', $this->campaignId)
            ->update(['is_active' => true]);

        DB::table('gameweeks')
            ->where('gameweek_id', $gameweek->gameweek_id)
            ->update([
                'status' => 'active',
                'prediction_open_at' => $now->subDay(),
                'prediction_close_at' => $now->addDay(),
            ]);

        DB::table('fixtures')
            ->whereIn('fixture_id', $this->fixtureIds)
            ->update(['fixture_status' => 'upcoming']);

        DB::table('predictions')
            ->where('profile_id', $this->profileId)
            ->whereIn('fixture_id', $this->fixtureIds)
            ->delete();
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

        $this->assertSame(count($this->fixtureIds), $saved);

        $count = DB::table('predictions')
            ->where('profile_id', $this->profileId)
            ->whereIn('fixture_id', $this->fixtureIds)
            ->count();

        $this->assertSame(count($this->fixtureIds), $count);
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

        $this->assertSame(count($this->fixtureIds), $initialCount);

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
            count($this->fixtureIds),
            DB::table('predictions')
                ->where('profile_id', $this->profileId)
                ->whereIn('fixture_id', $this->fixtureIds)
                ->count()
        );
    }
}

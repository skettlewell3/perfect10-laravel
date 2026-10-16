
<?php

namespace App\Services\Perfect10;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PredictionSubmissionService
{
    /**
     * @param array<int, array{
     *     fixture_id: int,
     *     pred_home_goals: int,
     *     pred_away_goals: int
     * }> $predictions
     */
    public function submit(
        string $profileId,
        int $campaignId,
        string $formatCode,
        array $predictions,
    ): int {
        if (! in_array($formatCode, ['GWK', 'PFX'], true)) {
            $this->reject('Unsupported prediction format.');
        }

        if (count($predictions) === 0) {
            $this->reject('No predictions were provided.');
        }

        $submittedIds = array_column($predictions, 'fixture_id');

        if (count($submittedIds) !== count(array_unique($submittedIds))) {
            $this->reject('Duplicate fixture IDs are not allowed.');
        }

        return DB::transaction(function () use (
            $profileId,
            $campaignId,
            $formatCode,
            $predictions,
        ): int {
            // Serialise submissions for this campaign.
            // This also prevents concurrent requests through this
            // service from bypassing the GWK completeness check.
            $campaign = DB::table('campaigns')
                ->where('campaign_id', $campaignId)
                ->lockForUpdate()
                ->first();

            if (! $campaign || ! $campaign->is_active) {
                $this->reject('The selected campaign is not active.');
            }

            $fixtureIds = array_column(
                $predictions,
                'fixture_id'
            );

            // Retrieve authoritative fixture and gameweek data.
            $fixtures = DB::table('fixtures as f')
                ->join(
                    'stages as s',
                    's.stage_id',
                    '=',
                    'f.stage_id'
                )
                ->leftJoin('gameweeks as gw', function ($join) {
                    $join->on(
                        'gw.stage_id',
                        '=',
                        'f.stage_id'
                    )->on(
                        'gw.campaign_id',
                        '=',
                        'f.campaign_id'
                    );
                })
                ->where('f.campaign_id', $campaignId)
                ->whereIn('f.fixture_id', $fixtureIds)
                ->select([
                    'f.fixture_id',
                    'f.fixture_status',
                    'f.prediction_open_at',
                    'f.prediction_close_at',
                    's.stage_type',
                    'gw.gameweek_id',
                    'gw.status as gameweek_status',
                    'gw.prediction_open_at as gw_open_at',
                    'gw.prediction_close_at as gw_close_at',
                ])
                ->orderBy('f.fixture_id')
                ->lockForUpdate()
                ->get();

            if ($fixtures->count() !== count($predictions)) {
                $this->reject(
                    'One or more fixtures do not belong to this campaign.'
                );
            }

            // Use the database clock, not the browser clock.
            $databaseTime = DB::selectOne(
                'SELECT clock_timestamp() AS current_time'
            );

            $now = CarbonImmutable::parse(
                $databaseTime->current_time
            );

            $gameweekIds = [];
            $hasStandalone = false;

            foreach ($fixtures as $fixture) {
                if ($fixture->fixture_status !== 'upcoming') {
                    $this->reject(
                        'Predictions can only be submitted for upcoming fixtures.'
                    );
                }

                if ($formatCode === 'GWK') {
                    if ($fixture->gameweek_id !== null) {
                        if ($fixture->stage_type !== 'league') {
                            $this->reject(
                                'This fixture does not belong to a valid gameweek stage.'
                            );
                        }

                        if ($fixture->gameweek_status !== 'active') {
                            $this->reject('The gameweek is not active.');
                        }

                        $gameweekIds[] = (int) $fixture->gameweek_id;

                        $openAt = $fixture->gw_open_at;
                        $closeAt = $fixture->gw_close_at;
                    } else {
                        // Rearranged fixtures are submitted
                        // independently from normal gameweeks.
                        if ($fixture->stage_type !== 'rearranged') {
                            $this->reject(
                                'This fixture is not eligible for standalone predictions.'
                            );
                        }

                        $hasStandalone = true;

                        $openAt = $fixture->prediction_open_at;
                        $closeAt = $fixture->prediction_close_at;
                    }
                } else {
                    // PFX always uses individual fixture windows.
                    $openAt = $fixture->prediction_open_at;
                    $closeAt = $fixture->prediction_close_at;
                }

                if (! $openAt || ! $closeAt) {
                    $this->reject(
                        'The prediction window is unavailable.'
                    );
                }

                $opens = CarbonImmutable::parse($openAt);
                $closes = CarbonImmutable::parse($closeAt);

                if (
                    $now->lessThan($opens) ||
                    ! $now->lessThan($closes)
                ) {
                    $this->reject(
                        'The prediction window is closed.'
                    );
                }
            }

            if ($formatCode === 'GWK') {
                $gameweekIds = array_values(
                    array_unique($gameweekIds)
                );

                if (count($gameweekIds) > 1) {
                    $this->reject(
                        'A submission cannot contain multiple gameweeks.'
                    );
                }

                if ($hasStandalone && count($gameweekIds) > 0) {
                    $this->reject(
                        'Standalone and gameweek predictions must be submitted separately.'
                    );
                }

                if (count($gameweekIds) === 1) {
                    $this->validateGameweekCompleteness(
                        $profileId,
                        $campaignId,
                        $gameweekIds[0],
                        $fixtureIds,
                    );
                }
            }

            $savedCount = 0;

            foreach ($predictions as $prediction) {
                $savedCount += DB::affectingStatement(
                    <<<'SQL'
                    INSERT INTO public.predictions (
                        profile_id,
                        fixture_id,
                        pred_home_goals,
                        pred_away_goals,
                        created_at,
                        updated_at
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON CONFLICT (profile_id, fixture_id)
                    DO UPDATE SET
                        pred_home_goals = EXCLUDED.pred_home_goals,
                        pred_away_goals = EXCLUDED.pred_away_goals,
                        updated_at = EXCLUDED.updated_at
                    WHERE
                        public.predictions.pred_home_goals IS DISTINCT FROM EXCLUDED.pred_home_goals
                        OR public.predictions.pred_away_goals IS DISTINCT FROM EXCLUDED.pred_away_goals
                    SQL,
                    [
                        $profileId,
                        $prediction['fixture_id'],
                        $prediction['pred_home_goals'],
                        $prediction['pred_away_goals'],
                        $now,
                        $now,
                    ]
                );
            }

            return $savedCount;
        }, 3);
    }

    /**
     * An initial GWK submission must produce a complete set.
     * Once complete, submitting only changed predictions is allowed.
     *
     * @param  array<int, int>  $submittedFixtureIds
     */
    private function validateGameweekCompleteness(
        string $profileId,
        int $campaignId,
        int $gameweekId,
        array $submittedFixtureIds,
    ): void {
        $requiredFixtureIds = DB::table('fixtures as f')
            ->join(
                'gameweeks as gw',
                function ($join) {
                    $join->on(
                        'gw.stage_id',
                        '=',
                        'f.stage_id'
                    )->on(
                        'gw.campaign_id',
                        '=',
                        'f.campaign_id'
                    );
                }
            )
            ->where('gw.gameweek_id', $gameweekId)
            ->where('f.campaign_id', $campaignId)
            ->pluck('f.fixture_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($requiredFixtureIds) === 0) {
            $this->reject(
                'The gameweek has no fixtures.'
            );
        }

        $existingFixtureIds = DB::table('predictions')
            ->where('profile_id', $profileId)
            ->whereIn('fixture_id', $requiredFixtureIds)
            ->pluck('fixture_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $existingComplete = count(
            array_diff(
                $requiredFixtureIds,
                $existingFixtureIds
            )
        ) === 0;

        if ($existingComplete) {
            return;
        }

        $resultingFixtureIds = array_unique([
            ...$existingFixtureIds,
            ...$submittedFixtureIds,
        ]);

        if (
            count(
                array_diff(
                    $requiredFixtureIds,
                    $resultingFixtureIds
                )
            ) > 0
        ) {
            $this->reject(
                'Your first gameweek submission must contain predictions for every fixture.'
            );
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages([
            'predictions' => $message,
        ]);
    }
}

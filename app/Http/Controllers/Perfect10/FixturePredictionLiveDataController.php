<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\Perfect10RequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FixturePredictionLiveDataController extends Controller
{
    public function __construct(
        private readonly Perfect10RequestContext $context,
        private readonly CurrentProfileService $currentProfile,
    ) {}

    public function show(string $flavour, string $fixture): JsonResponse
    {
        $fixtureId = (int) $fixture;

        abort_if($fixtureId < 1, 404);

        $campaignId = (int) $this->context
            ->campaign()
            ->getKey();

        $profileId = $this->currentProfile->profileId();

        $fixtureExists = DB::table('fixtures')
            ->where('fixture_id', $fixtureId)
            ->where('campaign_id', $campaignId)
            ->exists();

        abort_unless($fixtureExists, 404);

        if ($profileId === null) {
            return response()
                ->json(['prediction' => null])
                ->header('Cache-Control', 'no-store');
        }

        $prediction = DB::table('predictions as p')
            ->leftJoin(
                'prediction_scores as ps',
                'ps.prediction_id',
                '=',
                'p.prediction_id'
            )
            ->where('p.fixture_id', $fixtureId)
            ->where('p.profile_id', $profileId)
            ->first([
                'p.fixture_id',
                'p.prediction_id',
                'p.profile_id',
                'p.pred_home_goals',
                'p.pred_away_goals',
                'p.created_at as prediction_created_at',
                'p.updated_at as prediction_updated_at',
                'ps.correct_result',
                'ps.correct_home_goals',
                'ps.correct_away_goals',
                'ps.correct_goal_difference',
                'ps.correct_total_goals',
                'ps.points_total',
                'ps.perfect_10',
            ]);

        return response()
            ->json(['prediction' => $prediction])
            ->header('Cache-Control', 'no-store');
    }
}

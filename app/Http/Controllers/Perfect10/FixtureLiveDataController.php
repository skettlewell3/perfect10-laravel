<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Services\Perfect10\Perfect10RequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FixtureLiveDataController extends Controller
{
    public function __construct(
        private readonly Perfect10RequestContext $context,
    ) {}

    public function show(string $flavour, string $fixture): JsonResponse
    {
        $fixtureId = (int) $fixture;

        abort_if($fixtureId < 1, 404);

        $campaignId = (int) $this->context
            ->campaign()
            ->getKey();

        $row = DB::table('fixtures as f')
            ->leftJoin('results as r', 'r.fixture_id', '=', 'f.fixture_id')
            ->leftJoin('ties as t', 't.tie_id', '=', 'f.tie_id')
            ->where('f.fixture_id', $fixtureId)
            ->where('f.campaign_id', $campaignId)
            ->first([
                'f.fixture_id',
                'f.fixture_status',
                'f.prediction_open_at',
                'f.prediction_close_at',
                'r.ft_home_goals',
                'r.ft_away_goals',
                'r.final_home_goals',
                'r.final_away_goals',
                'r.result_type',
                't.winner_team_id',
                't.resolution_type',
            ]);

        abort_if($row === null, 404);

        return response()
            ->json([
                'fixture' => $row,
            ])
            ->header('Cache-Control', 'no-store');
    }
}
<?php

use App\Services\Perfect10\TeamComparisonContextService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    /*
     * Use an isolated in-memory cache for these tests.
     * No Redis, database cache or external service is required.
     */
    config(['cache.default' => 'array']);

    Cache::flush();
});

test('calls the comparison RPC with the correct flavour and team IDs', function () {
    DB::shouldReceive('select')
        ->once()
        ->with(
            'SELECT * FROM public.get_team_results_comparison(?, ?, ?)',
            [1, 3, 4]
        )
        ->andReturn([]);

    $service = new TeamComparisonContextService;

    $result = $service->compare(1, 3, 4);

    expect($result)->toBe([]);
});

test('converts database result rows into associative arrays', function () {
    $databaseRows = [
        (object) [
            'opponent_id' => 7,
            'opponent_name' => 'Fulham',
            'opponent_short' => 'FUL',
            't1_home_gf' => 2,
            't1_home_ga' => 1,
            't1_home_pts' => 3,
            't1_home_swing' => 3,
            't2_home_gf' => 0,
            't2_home_ga' => 1,
            't2_home_pts' => 0,
            't2_home_swing' => -3,
        ],
        (object) [
            'opponent_id' => 8,
            'opponent_name' => 'Everton',
            'opponent_short' => 'EVE',
            't1_home_gf' => null,
            't1_home_ga' => null,
            't1_home_pts' => null,
            't1_home_swing' => null,
            't2_home_gf' => 1,
            't2_home_ga' => 1,
            't2_home_pts' => 1,
            't2_home_swing' => null,
        ],
    ];

    DB::shouldReceive('select')
        ->once()
        ->andReturn($databaseRows);

    $service = new TeamComparisonContextService;

    $result = $service->compare(1, 3, 4);

    expect($result)
        ->toBe([
            [
                'opponent_id' => 7,
                'opponent_name' => 'Fulham',
                'opponent_short' => 'FUL',
                't1_home_gf' => 2,
                't1_home_ga' => 1,
                't1_home_pts' => 3,
                't1_home_swing' => 3,
                't2_home_gf' => 0,
                't2_home_ga' => 1,
                't2_home_pts' => 0,
                't2_home_swing' => -3,
            ],
            [
                'opponent_id' => 8,
                'opponent_name' => 'Everton',
                'opponent_short' => 'EVE',
                't1_home_gf' => null,
                't1_home_ga' => null,
                't1_home_pts' => null,
                't1_home_swing' => null,
                't2_home_gf' => 1,
                't2_home_ga' => 1,
                't2_home_pts' => 1,
                't2_home_swing' => null,
            ],
        ])
        ->and($result)->toBeArray()
        ->and($result[0])->toBeArray()
        ->and($result[1])->toBeArray();
});

test('caches repeated requests for the same comparison', function () {
    DB::shouldReceive('select')
        ->once()
        ->with(
            'SELECT * FROM public.get_team_results_comparison(?, ?, ?)',
            [1, 3, 4]
        )
        ->andReturn([
            (object) [
                'opponent_id' => 7,
                'opponent_name' => 'Fulham',
            ],
        ]);

    $service = new TeamComparisonContextService;

    $firstResult = $service->compare(1, 3, 4);
    $secondResult = $service->compare(1, 3, 4);

    expect($secondResult)
        ->toBe($firstResult)
        ->and($secondResult)->toHaveCount(1);
});

test('keeps reversed team comparisons in separate cache entries', function () {
    DB::shouldReceive('select')
        ->once()
        ->with(
            'SELECT * FROM public.get_team_results_comparison(?, ?, ?)',
            [1, 3, 4]
        )
        ->andReturn([
            (object) [
                'opponent_id' => 7,
                't1_total_points' => 3,
                't2_total_points' => 0,
            ],
        ]);

    DB::shouldReceive('select')
        ->once()
        ->with(
            'SELECT * FROM public.get_team_results_comparison(?, ?, ?)',
            [1, 4, 3]
        )
        ->andReturn([
            (object) [
                'opponent_id' => 7,
                't1_total_points' => 0,
                't2_total_points' => 3,
            ],
        ]);

    $service = new TeamComparisonContextService;

    $forward = $service->compare(1, 3, 4);
    $reversed = $service->compare(1, 4, 3);

    expect($forward[0]['t1_total_points'])
        ->toBe(3)
        ->and($reversed[0]['t1_total_points'])->toBe(0);

    /*
     * Both entries should now be cached, so these calls
     * must not trigger additional database queries.
     */
    expect($service->compare(1, 3, 4))
        ->toBe($forward)
        ->and($service->compare(1, 4, 3))->toBe($reversed);
});

test('keeps different flavours in separate cache entries', function () {
    DB::shouldReceive('select')
        ->once()
        ->with(
            'SELECT * FROM public.get_team_results_comparison(?, ?, ?)',
            [1, 3, 4]
        )
        ->andReturn([
            (object) [
                'opponent_id' => 7,
            ],
        ]);

    DB::shouldReceive('select')
        ->once()
        ->with(
            'SELECT * FROM public.get_team_results_comparison(?, ?, ?)',
            [2, 3, 4]
        )
        ->andReturn([
            (object) [
                'opponent_id' => 8,
            ],
        ]);

    $service = new TeamComparisonContextService;

    $firstFlavour = $service->compare(1, 3, 4);
    $secondFlavour = $service->compare(2, 3, 4);

    expect($firstFlavour[0]['opponent_id'])
        ->toBe(7)
        ->and($secondFlavour[0]['opponent_id'])->toBe(8);
});

test('fetches fresh data after invalidating a cached comparison', function () {
    DB::shouldReceive('select')
        ->twice()
        ->with(
            'SELECT * FROM public.get_team_results_comparison(?, ?, ?)',
            [1, 3, 4]
        )
        ->andReturn(
            [
                (object) [
                    'opponent_id' => 7,
                    't1_total_points' => 1,
                ],
            ],
            [
                (object) [
                    'opponent_id' => 7,
                    't1_total_points' => 3,
                ],
            ]
        );

    $service = new TeamComparisonContextService;

    $original = $service->compare(1, 3, 4);

    $service->forgetComparison(1, 3, 4);

    $refreshed = $service->compare(1, 3, 4);

    expect($original[0]['t1_total_points'])
        ->toBe(1)
        ->and($refreshed[0]['t1_total_points'])->toBe(3);
});

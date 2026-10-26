<?php

namespace App\Services\Perfect10;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TeamComparisonContextService
{
    private const CACHE_SECONDS = 10;

    /**
     * Retrieve the results comparison for two teams
     * within the selected flavour's active campaigns.
     *
     * The team order is preserved because the RPC uses
     * team 1 and team 2 to name its result columns.
     *
     * @return list<array<string, mixed>>
     */
    public function compare(
        int $flavourId,
        int $team1Id,
        int $team2Id,
    ): array {
        $cacheKey = "perfect10:team-comparison:v1:{$flavourId}:{$team1Id}:{$team2Id}";

        /** @var list<array<string, mixed>> $comparison */
        $comparison = Cache::remember(
            $cacheKey,
            self::CACHE_SECONDS,
            function () use ($flavourId, $team1Id, $team2Id): array {
                $rows = DB::select(
                    'SELECT * FROM public.get_team_results_comparison(?, ?, ?)',
                    [$flavourId, $team1Id, $team2Id]
                );

                $comparison = [];

                foreach ($rows as $row) {
                    $comparison[] = (array) $row;
                }

                return $comparison;
            }
        );

        return $comparison;
    }

    /**
     * Invalidate a cached comparison for a specific team pair.
     */
    public function forgetComparison(
        int $flavourId,
        int $team1Id,
        int $team2Id,
    ): void {
        Cache::forget(
            "perfect10:team-comparison:v1:{$flavourId}:{$team1Id}:{$team2Id}"
        );
    }
}

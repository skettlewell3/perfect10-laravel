<?php

namespace App\Services\Perfect10;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LeaderboardContextService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function leaderboard(
        int $flavourId,
        string $scopeType,
        ?int $scopeId,
        string $population,
    ): Collection {
        if (! in_array($scopeType, ['overall', 'campaign', 'stage'], true)) {
            throw new InvalidArgumentException(
                "Invalid leaderboard scope type: {$scopeType}"
            );
        }

        if (! in_array($population, ['global', 'users', 'clubs', 'bots'], true)) {
            throw new InvalidArgumentException(
                "Invalid leaderboard population: {$population}"
            );
        }

        $rows = DB::select(
            '
                SELECT *
                FROM public.get_from_leaderboard(
                    ?,
                    ?,
                    ?,
                    ?
                )
            ',
            [
                $flavourId,
                $scopeType,
                $scopeId,
                $population,
            ]
        );

        $result = collect($rows)
            ->map(fn (object $row): array => get_object_vars($row))
            ->values();

        /** @var Collection<int, array<string, mixed>> $result */
        return $result;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function clubLeaderboard(
        string $clubId,
        int $flavourId,
    ): Collection {
        $rows = DB::select(
            '
                WITH scoped_profiles AS (
                    SELECT cm.profile_id
                    FROM club_memberships cm
                    WHERE cm.club_id = ?
        
                    UNION
        
                    SELECT ?::uuid
                )
        
                SELECT
                    DENSE_RANK() OVER (
                        ORDER BY
                            lb.points_total DESC,
                            lb.perfect_10s DESC,
                            lb.correct_result DESC,
                            lb.display_name ASC
                    ) AS rank,
        
                    lb.profile_id,
                    lb.display_name,
                    lb.profile_type,
                    lb.predicted,
                    lb.correct_result AS correct_results,
                    lb.correct_home_goals,
                    lb.correct_away_goals,
                    lb.correct_goal_difference,
                    lb.correct_total_goals,
                    lb.perfect_10s,
                    lb.points_total
        
                FROM leaderboard lb
        
                JOIN scoped_profiles sp
                    ON sp.profile_id = lb.profile_id
        
                WHERE
                    lb.flavour_id = ?
                    AND lb.scope_type = \'overall\'
                    AND lb.scope_id IS NULL
        
                ORDER BY
                    lb.points_total DESC,
                    lb.perfect_10s DESC,
                    lb.correct_result DESC,
                    lb.display_name ASC
            ',
            [
                $clubId,
                $clubId,
                $flavourId,
            ]
        );

        $result = collect($rows)
            ->map(fn (object $row): array => get_object_vars($row))
            ->values();

        /** @var Collection<int, array<string, mixed>> $result */
        return $result;
    }
}

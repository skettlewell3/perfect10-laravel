<?php

namespace App\Services\Perfect10;

use Illuminate\Support\Facades\DB;

class FixtureLeaderboardContextService
{
    /**
     * Retrieve the ranked prediction scores for one fixture.
     *
     * @return list<array<string, mixed>>
     */
    public function leaderboardForFixture(int $fixtureId): array
    {
        $rows = DB::select(
            'SELECT * FROM public.get_fixture_leaderboard(?)',
            [$fixtureId]
        );

        $leaderboard = [];

        foreach ($rows as $row) {
            $leaderboard[] = (array) $row;
        }

        return $leaderboard;
    }
}

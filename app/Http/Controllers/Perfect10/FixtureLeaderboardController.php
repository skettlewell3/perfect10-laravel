<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Services\Perfect10\FixtureLeaderboardContextService;
use App\Services\Perfect10\Perfect10RequestContext;
use Illuminate\Http\JsonResponse;

class FixtureLeaderboardController extends Controller
{
    public function __construct(
        private Perfect10RequestContext $context,
        private FixtureLeaderboardContextService $fixtureLeaderboardContext,
    ) {}

    public function show(string $flavour, string $fixture): JsonResponse
    {
        /*
         * Route:
         *
         * /{flavour}/fixtures/{fixture}/leaderboard
         *
         * The selected flavour is resolved by
         * Perfect10RequestContext.
         */
        abort_unless(
            ctype_digit($fixture) && (int) $fixture > 0,
            404,
            'Invalid fixture ID.'
        );

        $fixtureId = (int) $fixture;

        /*
         * Perfect10RequestContext provides fixtures belonging
         * to the selected flavour's active campaign scope.
         *
         * This prevents requesting a leaderboard for a
         * fixture outside the selected flavour.
         */
        $currentFixture = collect($this->context->fixtures())
            ->first(
                fn (array $item): bool => (
                    (int) $item['fixture_id'] === $fixtureId
                )
            );

        abort_if(
            $currentFixture === null,
            404,
            'Fixture not found for the selected flavour.'
        );

        /*
         * Leaderboards are available only once the
         * fixture has started, including extra time,
         * and after it has finished.
         *
         * Upcoming and postponed fixtures are excluded.
         */
        abort_unless(
            in_array(
                $currentFixture['fixture_status'],
                ['live_90', 'live_et', 'finished'],
                true
            ),
            404,
            'The leaderboard is not available for this fixture.'
        );

        /*
         * The RPC handles ranking, prediction scores,
         * profile filtering and score ordering.
         */
        $leaderboard = $this->fixtureLeaderboardContext
            ->leaderboardForFixture($fixtureId);

        return response()->json([
            'fixture_id' => $fixtureId,
            'leaderboard' => $leaderboard,
        ]);
    }
}

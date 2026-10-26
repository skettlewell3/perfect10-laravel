<?php

namespace App\Services\Perfect10;

class MatchFormContextService
{
    /**
     * @param  list<array<string, mixed>>  $fixtures
     * @param  array<string, mixed>  $currentFixture
     * @return array{
     *     home: array{past: list<array<string, mixed>>, future: list<array<string, mixed>>},
     *     away: array{past: list<array<string, mixed>>, future: list<array<string, mixed>>}
     * }
     */
    public function forMatch(
        array $fixtures,
        array $currentFixture,
        int $pastCount = 5,
        int $futureCount = 2,
    ): array {
        $anchorFixtureId = (int) $currentFixture['fixture_id'];

        return [
            'home' => $this->forTeam(
                $fixtures,
                (int) $currentFixture['home_team_id'],
                $anchorFixtureId,
                $pastCount,
                $futureCount,
            ),
            'away' => $this->forTeam(
                $fixtures,
                (int) $currentFixture['away_team_id'],
                $anchorFixtureId,
                $pastCount,
                $futureCount,
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fixtures
     * @return array{
     *     past: list<array<string, mixed>>,
     *     future: list<array<string, mixed>>
     * }
     */
    public function forTeam(
        array $fixtures,
        int $teamId,
        int $anchorFixtureId,
        int $pastCount = 5,
        int $futureCount = 2,
    ): array {
        $teamFixtures = collect($fixtures)
            ->filter(fn (array $fixture): bool => (
                (int) ($fixture['home_team_id'] ?? 0) === $teamId ||
                (int) ($fixture['away_team_id'] ?? 0) === $teamId
            ))
            ->sort(fn (array $a, array $b): int => (
                strcmp(
                    (string) $a['kickoff_at'],
                    (string) $b['kickoff_at'],
                ) ?: (
                    (int) $a['fixture_id'] <=>
                    (int) $b['fixture_id']
                )
            ))
            ->values();

        $anchorIndex = $teamFixtures->search(
            fn (array $fixture): bool => (
                (int) $fixture['fixture_id'] === $anchorFixtureId
            )
        );

        if ($anchorIndex === false) {
            return [
                'past' => [],
                'future' => [],
            ];
        }

        return [
            'past' => array_values(
                $teamFixtures
                    ->take($anchorIndex)
                    ->take(-$pastCount)
                    ->reverse()
                    ->all()
            ),

            'future' => array_values(
                $teamFixtures
                    ->slice($anchorIndex + 1, $futureCount)
                    ->all()
            ),
        ];
    }
}

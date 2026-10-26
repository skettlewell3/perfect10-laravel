<?php

namespace App\Services\Perfect10;

use App\Models\Perfect10\Team;
use Illuminate\Support\Collection;

class TeamContextService
{
    /**
     * Retrieve team presentation data for a collection of fixtures.
     *
     * @param  list<array<string, mixed>>  $fixtures
     * @return Collection<int, array{
     *     team_id: int,
     *     team_name: string,
     *     flag_code: string|null,
     *     color_1: string|null,
     *     color_2: string|null,
     *     color_3: string|null
     * }>
     */
    public function teamsMapForFixtures(array $fixtures): Collection
    {
        $teamIds = collect($fixtures)
            ->flatMap(fn (array $fixture): array => [
                $fixture['home_team_id'],
                $fixture['away_team_id'],
            ])
            ->filter(fn ($id): bool => $id !== null)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($teamIds->isEmpty()) {
            return collect();
        }

        return Team::query()
            ->whereIn('team_id', $teamIds)
            ->with([
                'nationalProfile:team_id,flag_code',
            ])
            ->get([
                'team_id',
                'team_name',
                'color_1',
                'color_2',
                'color_3',
            ])
            ->mapWithKeys(function (Team $team): array {
                return [
                    (int) $team->team_id => [
                        'team_id' => $team->team_id,
                        'team_name' => $team->team_name,
                        'flag_code' => $team->nationalProfile?->flag_code,
                        'color_1' => $team->color_1,
                        'color_2' => $team->color_2,
                        'color_3' => $team->color_3,
                    ],
                ];
            });
    }
}

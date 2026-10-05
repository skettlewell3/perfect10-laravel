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
}

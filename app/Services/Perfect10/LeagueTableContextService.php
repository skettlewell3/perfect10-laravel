<?php

namespace App\Services\Perfect10;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeagueTableContextService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function actualTable(
        int $flavourId
    ): Collection {
        $rows = DB::select(
            '
                SELECT *
                FROM public.get_league_table_actual(?)
            ',
            [
                $flavourId,
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
    public function predictedTable(
        int $flavourId,
        string $profileId
    ): Collection {
        $rows = DB::select(
            '
                SELECT *
                FROM public.get_league_table_prediction(
                    ?,
                    ?::uuid
                )
            ',
            [
                $flavourId,
                $profileId,
            ]
        );

        $result = collect($rows)
            ->map(fn (object $row): array => get_object_vars($row))
            ->values();

        /** @var Collection<int, array<string, mixed>> $result */
        return $result;
    }
}
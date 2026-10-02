<?php

namespace App\Services\Perfect10;

use App\Models\Perfect10\Flavour;
use Illuminate\Support\Facades\DB;

class FixtureContextService
{
    /**
     * Retrieve the existing fixture-provider data
     * for the selected prediction flavour.
     *
     * @return list<array<string, mixed>>
     */
    public function fixturesForFlavour(Flavour $flavour): array
    {
        $rows = DB::select(
            'SELECT * FROM public.get_fixture_provider(?)',
            [(int) $flavour->getKey()]
        );

        $fixtures = [];

        foreach ($rows as $row) {
            $fixtures[] = (array) $row;
        }

        return $fixtures;
    }
}

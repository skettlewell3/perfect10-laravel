<?php

namespace App\Services\Perfect10;

use App\Models\Perfect10\Flavour;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FixtureContextService
{
    private const CACHE_SECONDS = 10;

    /**
     * Retrieve fixture-provider data for the selected flavour.
     *
     * The provider result is cached across requests for a short
     * period. Request-level reuse and grouping are handled by
     * Perfect10RequestContext.
     *
     * @return list<array<string, mixed>>
     */
    public function fixturesForFlavour(Flavour $flavour): array
    {
        $flavourId = (int) $flavour->getKey();

        $cacheKey = "perfect10:fixture-provider:v1:{$flavourId}";

        /** @var list<array<string, mixed>> $fixtures */
        $fixtures = Cache::remember(
            $cacheKey,
            self::CACHE_SECONDS,
            function () use ($flavourId): array {
                $rows = DB::select(
                    'SELECT * FROM public.get_fixture_provider(?)',
                    [$flavourId]
                );

                $fixtures = [];

                foreach ($rows as $row) {
                    $fixtures[] = (array) $row;
                }

                return $fixtures;
            }
        );

        return $fixtures;
    }

    /**
     * Invalidate the cached fixture-provider data for a flavour.
     */
    public function forgetFixturesForFlavour(int $flavourId): void
    {
        Cache::forget(
            "perfect10:fixture-provider:v1:{$flavourId}"
        );
    }
}

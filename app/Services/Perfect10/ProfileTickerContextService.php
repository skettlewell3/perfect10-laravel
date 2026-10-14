<?php

namespace App\Services\Perfect10;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfileTickerContextService
{
    /**
     * @return Collection<int, object>
     */
    public function ticker(
        string $profileId,
        int $flavourId,
        int $campaignId,
        int $stageId
    ): Collection {
        return collect(
            DB::select(
                '
                    select *
                    from public.get_profile_ticker(
                        ?::uuid,
                        ?,
                        ?,
                        ?
                    )
                ',
                [
                    $profileId,
                    $flavourId,
                    $campaignId,
                    $stageId,
                ]
            )
        );
    }
}

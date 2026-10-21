<?php

namespace App\Services\Perfect10;

use App\Models\Perfect10\Flavour;
use Illuminate\Support\Facades\DB;

class PredictionContextService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function predictionsForFlavour(
        Flavour $flavour,
        ?string $profileId
    ): array {
        if ($profileId === null) {
            return [];
        }

        /** @var array<int, object{fixture_id: int|string}> $rows */
        $rows = DB::select(
            'SELECT * FROM public.get_user_flavour_predictions(?, ?::uuid)',
            [
                (int) $flavour->getKey(),
                $profileId,
            ]
        );

        return collect($rows)
            ->mapWithKeys(function (object $row): array {
                return [
                    (int) $row->fixture_id => (array) $row,
                ];
            })
            ->all();
    }
}

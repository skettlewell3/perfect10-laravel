<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\LeagueTableContextService;
use App\Services\Perfect10\Perfect10RequestContext;
use Inertia\Inertia;
use Inertia\Response;

class StatsController extends Controller
{
    public function __construct(
        private Perfect10RequestContext $context,
        private LeagueTableContextService $leagueTableContext,
        private CurrentProfileService $currentProfile,
    ) {}

    public function index(): Response
    {
        $selectedFlavourData = $this->context
            ->selectedFlavourData();

        $isGameweekFormat =
            $selectedFlavourData['format_code'] === 'GWK';

        $actualTable = collect();
        $predictedTable = collect();

        if ($isGameweekFormat) {
            $flavourId = (int) $selectedFlavourData['flavour_id'];

            $actualTable = $this->leagueTableContext
                ->actualTable($flavourId);

            $profileId = $this->currentProfile->profileId();

            if ($profileId !== null) {
                $predictedTable = $this->leagueTableContext
                    ->predictedTable(
                        $flavourId,
                        $profileId
                    );
            }
        }

        return Inertia::render('perfect10/stats', [
            'flavour' => $selectedFlavourData,
            'actualTable' => $actualTable,
            'predictedTable' => $predictedTable,
        ]);
    }
}

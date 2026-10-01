<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Models\Perfect10\Flavour;
use App\Services\Perfect10\FlavourContextService;
use App\Services\Perfect10\GameweekContextService;
use App\Services\Perfect10\StageContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private FlavourContextService $flavourContext,
        private StageContextService $stageContext,
        private GameweekContextService $gameweekContext,
    ) {}

    public function index(Request $request): Response
    {
        $flavours = $this->flavourContext->availableFlavours();

        abort_if(
            $flavours->isEmpty(),
            404,
            'No active Perfect10 flavour is available.'
        );

        $requestedFlavourCode = trim(
            $request->string('flavour')->toString()
        );

        $selectedFlavourData = $requestedFlavourCode !== ''
            ? $flavours->firstWhere(
                'flavour_code',
                $requestedFlavourCode
            )
            : null;

        $selectedFlavourData ??=
            $flavours->firstWhere('is_default', true)
            ?? $flavours->first();

        $flavourId = (int) $selectedFlavourData['flavour_id'];

        $flavour = Flavour::query()
            ->findOrFail($flavourId);

        $competition = $this->flavourContext
            ->competitionFor($flavour);

        $campaign = $this->flavourContext
            ->activeCampaignFor($flavour);

        // Universal stage context: both prediction formats.

        $stages = $this->stageContext
            ->stagesForCampaign($campaign);

        $activeStage = $this->stageContext
            ->activeStage($stages);

        $tickerStage = $this->stageContext
            ->tickerStage($stages);

        // Gameweek context: GWK prediction formats only.

        $gameweeks = null;
        $activeGameweek = null;
        $nextGameweek = null;

        if ($selectedFlavourData['format_code'] === 'GWK') {
            $gameweeks = $this->gameweekContext
                ->gameweeksForCampaign($campaign);

            $activeGameweek = $this->gameweekContext
                ->validatedActiveGameweek(
                    $gameweeks,
                    $activeStage
                );

            $nextGameweek = $this->gameweekContext
                ->nextGameweek($gameweeks);
        }

        return Inertia::render('perfect10/dashboard', [
            'flavours' => $flavours,
            'flavour' => $selectedFlavourData,
            'competition' => $competition,
            'campaign' => $campaign,

            'stageContext' => [
                'stages' => $stages,
                'activeStage' => $activeStage,
                'tickerStage' => $tickerStage,
            ],

            'gameweekContext' => $gameweeks === null
                ? null
                : [
                    'gameweeks' => $gameweeks,
                    'activeGameweek' => $activeGameweek,
                    'nextGameweek' => $nextGameweek,
                ],
        ]);
    }
}

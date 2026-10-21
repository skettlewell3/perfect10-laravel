<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Services\Perfect10\FixtureContextService;
use App\Services\Perfect10\GameweekContextService;
use App\Services\Perfect10\Perfect10RequestContext;
use App\Services\Perfect10\StageContextService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private Perfect10RequestContext $context,
        private FixtureContextService $fixtureContext,
        private StageContextService $stageContext,
        private GameweekContextService $gameweekContext,
    ) {}

    public function index(): Response
    {
        $flavours = $this->context->flavours();

        $selectedFlavourData = $this->context
            ->selectedFlavourData();

        $flavour = $this->context->flavour();

        $competition = $this->context->competition();

        $campaign = $this->context->campaign();

        $stages = $this->context->stages();

        $fixtures = $this->fixtureContext
            ->fixturesForFlavour($flavour);

        // Universal stage context: both prediction formats.

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
            'fixtures' => $fixtures,

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

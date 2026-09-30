<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Models\Perfect10\Flavour;
use App\Services\Perfect10\FlavourContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private FlavourContextService $flavourContext
    ) {}

    public function index(Request $request): Response
    {
        $flavours = $this->flavourContext->availableFlavours();

        abort_if(
            $flavours->isEmpty(),
            404,
            'No active Perfect10 flavour is available.'
        );

        $requestedFlavourCode = $request->string('flavour')->toString();

        $selectedFlavourData = $requestedFlavourCode !== ''
            ? $flavours->firstWhere('flavour_code', $requestedFlavourCode)
            : null;

        $selectedFlavourData ??= $flavours->firstWhere('is_default', true)
            ?? $flavours->first();

        $flavourId = (int) $selectedFlavourData['flavour_id'];

        $flavour = Flavour::query()
            ->findOrFail($flavourId);

        $competition = $this->flavourContext->competitionFor($flavour);
        $campaign = $this->flavourContext->activeCampaignFor($flavour);

        return Inertia::render('perfect10/dashboard', [
            'flavours' => $flavours,
            'flavour' => $selectedFlavourData,
            'competition' => $competition,
            'campaign' => $campaign,
        ]);
    }
}

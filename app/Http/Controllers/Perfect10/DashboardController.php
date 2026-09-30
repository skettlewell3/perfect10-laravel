<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Models\Perfect10\Flavour;
use App\Services\Perfect10\FlavourContextService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private FlavourContextService $flavourContext
    ) {}

    public function index(): Response
    {
        $flavours = Flavour::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('flavour_name')
            ->get();

        $flavour = $flavours->firstWhere('is_default', true)
            ?? $flavours->first();

        abort_if($flavour === null, 404, 'No active Perfect10 flavour is available.');

        $competition = $this->flavourContext->competitionFor($flavour);
        $campaign = $this->flavourContext->activeCampaignFor($flavour);

        return Inertia::render('perfect10/dashboard', [
            'flavours' => $flavours,
            'flavour' => $flavour,
            'competition' => $competition,
            'campaign' => $campaign,
        ]);
    }
}

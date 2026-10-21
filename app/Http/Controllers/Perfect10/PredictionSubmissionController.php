<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\Perfect10RequestContext;
use App\Services\Perfect10\PredictionSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PredictionSubmissionController extends Controller
{
    public function __construct(
        private Perfect10RequestContext $context,
        private CurrentProfileService $currentProfile,
        private PredictionSubmissionService $submissionService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        // Identity comes from the authenticated Laravel session.
        $profileId = $this->currentProfile->profileId();

        abort_unless(
            $profileId !== null,
            403,
            'A Perfect10 profile is required to submit predictions.'
        );

        $flavours = $this->context->flavours();

        $flavour = $this->context->selectedFlavourData();

        abort_unless(
            ! $flavours->isEmpty() &&
            $flavours->contains(
                fn ($item) => $item['flavour_code'] === $flavour['flavour_code']
            ) &&
            strcasecmp(
                $flavour['flavour_code'],
                (string) $request->route('flavour')
            ) === 0,
            404,
            'The selected flavour is unavailable.'
        );

        abort_unless(
            $flavour['active_campaign_id'] !== null,
            404,
            'No active campaign is available.'
        );

        $validated = $request->validate([
            'predictions' => [
                'required',
                'array',
                'min:1',
                'max:104',
            ],
            'predictions.*' => [
                'required',
                'array',
            ],
            'predictions.*.fixture_id' => [
                'required',
                'integer',
                'distinct',
            ],
            'predictions.*.pred_home_goals' => [
                'required',
                'integer',
                'between:0,9',
            ],
            'predictions.*.pred_away_goals' => [
                'required',
                'integer',
                'between:0,9',
            ],
        ]);

        $count = $this->submissionService->submit(
            profileId: $profileId,
            campaignId: (int) $flavour['active_campaign_id'],
            formatCode: $flavour['format_code'],
            predictions: $validated['predictions'],
        );

        return back()->with(
            'success',
            "{$count} prediction(s) saved successfully."
        );
    }
}

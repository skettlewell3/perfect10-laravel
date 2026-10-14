<?php

namespace App\Services\Perfect10;

use App\Models\Perfect10\Flavour;

class Perfect10ShellContextService
{
    public function __construct(
        private CurrentProfileService $currentProfile,
        private FlavourContextService $flavourContext,
        private StageContextService $stageContext,
        private ProfileTickerContextService $profileTickerContext,
    ) {}

    /**
     * @return array{
     *     global: object|null,
     *     flavour: object|null,
     *     campaign: object|null,
     *     stage: object|null
     * }
     */
    public function profileTicker(string $requestedFlavour): array
    {
        $emptyTicker = [
            'global' => null,
            'flavour' => null,
            'campaign' => null,
            'stage' => null,
        ];

        $profileId = $this->currentProfile->profileId();

        if ($profileId === null) {
            return $emptyTicker;
        }

        $flavours = $this->flavourContext
            ->availableFlavours();

        if ($flavours->isEmpty()) {
            return $emptyTicker;
        }

        $selectedFlavourData = $this->flavourContext
            ->selectFlavour(
                $flavours,
                $requestedFlavour
            );

        $flavourId = (int) $selectedFlavourData['flavour_id'];

        $flavour = Flavour::query()
            ->findOrFail($flavourId);

        $campaign = $this->flavourContext
            ->activeCampaignFor($flavour);

        $stages = $this->stageContext
            ->stagesForCampaign($campaign);

        $tickerStage = $this->stageContext
            ->tickerStage($stages);

        if ($tickerStage === null) {
            return $emptyTicker;
        }

        $ticker = $this->profileTickerContext
            ->ticker(
                $profileId,
                $flavourId,
                $campaign->campaign_id,
                $tickerStage->stage_id
            );

        return [
            'global' => $ticker->firstWhere(
                'scope',
                'global'
            ),
            'flavour' => $ticker->firstWhere(
                'scope',
                'flavour'
            ),
            'campaign' => $ticker->firstWhere(
                'scope',
                'campaign'
            ),
            'stage' => $ticker->firstWhere(
                'scope',
                'stage'
            ),
        ];
    }
}
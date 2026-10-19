<?php

namespace App\Services\Perfect10;

class Perfect10ShellContextService
{
    public function __construct(
        private CurrentProfileService $currentProfile,
        private Perfect10RequestContext $context,
        private StageContextService $stageContext,
        private ProfileTickerContextService $profileTickerContext,
    ) {}

    /**
     * @return array{
     *     tickerStageLabel: string|null,
     *     global: object|null,
     *     flavour: object|null,
     *     campaign: object|null,
     *     stage: object|null
     * }
     */
    public function profileTicker(string $requestedFlavour): array
    {
        $emptyTicker = [
            'tickerStageLabel' => null,
            'global' => null,
            'flavour' => null,
            'campaign' => null,
            'stage' => null,
        ];

        $profileId = $this->currentProfile->profileId();

        if ($profileId === null) {
            return $emptyTicker;
        }

        $selectedFlavourData = $this->context->selectedFlavourData();

        $flavourId = (int) $selectedFlavourData['flavour_id'];
        $campaign = $this->context->campaign();
        $stages = $this->context->stages();

        $tickerStage = $this->stageContext->tickerStage($stages);

        if ($tickerStage === null) {
            return $emptyTicker;
        }

        $ticker = $this->profileTickerContext->ticker(
            $profileId,
            $flavourId,
            (int) $campaign->campaign_id,
            (int) $tickerStage->stage_id
        );

        return [
            'tickerStageLabel' => $tickerStage->stage_name,
            'global' => $ticker->firstWhere('scope', 'global'),
            'flavour' => $ticker->firstWhere('scope', 'flavour'),
            'campaign' => $ticker->firstWhere('scope', 'campaign'),
            'stage' => $ticker->firstWhere('scope', 'stage'),
        ];
    }
}


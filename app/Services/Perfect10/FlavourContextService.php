<?php

namespace App\Services\Perfect10;

use App\Models\Perfect10\Campaign;
use App\Models\Perfect10\Competition;
use App\Models\Perfect10\Flavour;
use App\Models\Perfect10\PredictionFormat;
use Illuminate\Support\Collection;
use RuntimeException;

class FlavourContextService
{
    public function competitionFor(Flavour $flavour): Competition
    {
        $competitions = $flavour
            ->competitionLink()
            ->get();

        if ($competitions->count() !== 1) {
            throw new RuntimeException(
                "Flavour {$flavour->flavour_id} must resolve to exactly one competition."
            );
        }

        return $competitions->first();
    }

    public function predictionFormatFor(Flavour $flavour): PredictionFormat
    {
        $format = $flavour
            ->format()
            ->first();

        if ($format === null) {
            throw new RuntimeException(
                "Flavour {$flavour->flavour_id} must resolve to a prediction format."
            );
        }

        return $format;
    }

    public function activeCampaignFor(Flavour $flavour): Campaign
    {
        $competition = $this->competitionFor($flavour);

        $campaigns = $competition
            ->campaigns()
            ->where('is_active', true)
            ->get();

        if ($campaigns->count() !== 1) {
            throw new RuntimeException(
                "Competition {$competition->competition_id} must resolve to exactly one active campaign."
            );
        }

        return $campaigns->first();
    }

    /**
     * @return Collection<int, array{
     *     flavour_id: int,
     *     flavour_name: string,
     *     flavour_code: string,
     *     is_default: bool,
     *     competition_id: int,
     *     competition_name: string,
     *     competition_code: string,
     *     format_id: int,
     *     format_name: string,
     *     format_code: string,
     *     active_campaign_id: int,
     *     active_campaign_code: string,
     *     active_campaign_label: string
     * }>
     */
    public function availableFlavours(): Collection
    {
        return Flavour::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('flavour_name')
            ->get()
            ->toBase()
            ->map(function (Flavour $flavour): array {
                $competition = $this->competitionFor($flavour);
                $format = $this->predictionFormatFor($flavour);
                $campaign = $this->activeCampaignFor($flavour);

                return [
                    'flavour_id' => (int) $flavour->getAttribute('flavour_id'),
                    'flavour_name' => (string) $flavour->getAttribute('flavour_name'),
                    'flavour_code' => trim((string) $flavour->getAttribute('flavour_code')),
                    'is_default' => (bool) $flavour->getAttribute('is_default'),

                    'competition_id' => (int) $competition->getAttribute('competition_id'),
                    'competition_name' => (string) $competition->getAttribute('competition_name'),
                    'competition_code' => trim((string) $competition->getAttribute('competition_code')),

                    'format_id' => (int) $format->getAttribute('format_id'),
                    'format_name' => (string) $format->getAttribute('format_name'),
                    'format_code' => trim((string) $format->getAttribute('format_code')),

                    'active_campaign_id' => (int) $campaign->getAttribute('campaign_id'),
                    'active_campaign_code' => (string) $campaign->getAttribute('code'),
                    'active_campaign_label' => (string) $campaign->getAttribute('label'),
                ];
            })
            ->values();
    }

    /**
     * @template TFlavour of array<string, mixed>
     *
     * @param  Collection<int, TFlavour>  $flavours
     * @return TFlavour
     */
    public function selectFlavour(
        Collection $flavours,
        ?string $requestedCode = null
    ): array {
        if ($flavours->isEmpty()) {
            throw new RuntimeException(
                'No active Perfect10 flavour is available.'
            );
        }

        $requestedCode = trim($requestedCode ?? '');

        $selected = $requestedCode !== ''
            ? $flavours->firstWhere(
                'flavour_code',
                $requestedCode
            )
            : null;

        return $selected
            ?? $flavours->firstWhere('is_default', true)
            ?? $flavours->first();
    }
}

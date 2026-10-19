<?php

namespace App\Services\Perfect10;

use App\Models\Perfect10\Campaign;
use App\Models\Perfect10\Competition;
use App\Models\Perfect10\Flavour;
use App\Models\Perfect10\PredictionFormat;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use RuntimeException;

class FlavourContextService
{
    private ?Collection $availableFlavours = null;

    private ?EloquentCollection $flavourModels = null;

    public function competitionFor(Flavour $flavour): Competition
    {
        $competitions = $flavour->relationLoaded('competitionLink')
            ? $flavour->getRelation('competitionLink')
            : $flavour->competitionLink()->get();

        if ($competitions->count() !== 1) {
            throw new RuntimeException(
                "Flavour {$flavour->flavour_id} must resolve to exactly one competition."
            );
        }

        return $competitions->first();
    }

    public function predictionFormatFor(Flavour $flavour): PredictionFormat
    {
        $format = $flavour->relationLoaded('format')
            ? $flavour->getRelation('format')
            : $flavour->format()->first();

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

        $campaigns = $competition->relationLoaded('campaigns')
            ? $competition->getRelation('campaigns')
                ->where('is_active', true)
            : $competition->campaigns()
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
     * Retrieve and retain the active flavour models.
     *
     * Relationships are eager-loaded in batches so the selected
     * flavour can be reused without additional database queries.
     *
     * @return EloquentCollection<int, Flavour>
     */
    private function loadFlavourModels(): EloquentCollection
    {
        if ($this->flavourModels !== null) {
            return $this->flavourModels;
        }

        return $this->flavourModels = Flavour::query()
            ->with([
                'competitionLink.campaigns' => function ($query) {
                    $query->where('is_active', true);
                },
                'format',
            ])
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('flavour_name')
            ->get();
    }

    /**
     * Return an already-loaded active flavour model.
     *
     * @throws ModelNotFoundException
     */
    public function activeFlavourModel(int $flavourId): Flavour
    {
        $flavour = $this->loadFlavourModels()
            ->first(
                fn (Flavour $flavour): bool =>
                    (int) $flavour->getKey() === $flavourId
            );

        if ($flavour === null) {
            throw (new ModelNotFoundException)
                ->setModel(Flavour::class, [$flavourId]);
        }

        return $flavour;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function availableFlavours(): Collection
    {
        if ($this->availableFlavours !== null) {
            return $this->availableFlavours;
        }

        $this->availableFlavours = $this->loadFlavourModels()
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

        return $this->availableFlavours;
    }

    /**
     * @template TFlavour of array<string, mixed>
     *
     * @param Collection<int, TFlavour> $flavours
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

        $requestedCode = strtoupper(
            trim($requestedCode ?? '')
        );

        $selected = $requestedCode !== ''
            ? $flavours->first(
                fn (array $flavour) => strtoupper(
                    $flavour['flavour_code']
                ) === $requestedCode
            )
            : null;

        return $selected
            ?? $flavours->firstWhere('is_default', true)
            ?? $flavours->first();
    }
}

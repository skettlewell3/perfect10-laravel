<?php

namespace App\Services\Perfect10;

use App\Models\Perfect10\Campaign;
use App\Models\Perfect10\Competition;
use App\Models\Perfect10\Flavour;
use App\Models\Perfect10\Stage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class Perfect10RequestContext
{
    private ?Collection $flavours = null;

    private ?array $selectedFlavourData = null;

    private ?Flavour $flavour = null;

    private ?Competition $competition = null;

    private ?Campaign $campaign = null;

    private ?EloquentCollection $stages = null;

    public function __construct(
        private readonly Request $request,
        private readonly FlavourContextService $flavourContext,
        private readonly StageContextService $stageContext,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function flavours(): Collection
    {
        return $this->flavours ??= $this->flavourContext
            ->availableFlavours();
    }

    /**
     * @return array<string, mixed>
     */
    public function selectedFlavourData(): array
    {
        if ($this->selectedFlavourData !== null) {
            return $this->selectedFlavourData;
        }

        $flavours = $this->flavours();

        abort_if(
            $flavours->isEmpty(),
            404,
            'No active Perfect10 flavour is available.'
        );

        return $this->selectedFlavourData = $this->flavourContext
            ->selectFlavour(
                $flavours,
                (string) $this->request->route('flavour')
            );
    }

    public function flavour(): Flavour
    {
        return $this->flavour ??= $this->flavourContext
            ->activeFlavourModel(
                (int) $this->selectedFlavourData()['flavour_id']
            );
    }

    public function competition(): Competition
    {
        return $this->competition ??= $this->flavourContext
            ->competitionFor($this->flavour());
    }

    public function campaign(): Campaign
    {
        return $this->campaign ??= $this->flavourContext
            ->activeCampaignFor($this->flavour());
    }

    /**
     * @return EloquentCollection<int, Stage>
     */
    public function stages(): EloquentCollection
    {
        return $this->stages ??= $this->stageContext
            ->stagesForCampaign($this->campaign());
    }
}

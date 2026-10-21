<?php

namespace App\Services\Perfect10;

use App\Models\Perfect10\Campaign;
use App\Models\Perfect10\Competition;
use App\Models\Perfect10\Flavour;
use App\Models\Perfect10\Stage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * @phpstan-import-type FlavourData from FlavourContextService
 */
class Perfect10RequestContext
{
    /**
     * @var Collection<int, FlavourData>|null
     */
    private ?Collection $flavours = null;

    /**
     * @var FlavourData|null
     */
    private ?array $selectedFlavourData = null;

    private ?Flavour $flavour = null;

    private ?Competition $competition = null;

    private ?Campaign $campaign = null;

    /**
     * @var EloquentCollection<int, Stage>|null
     */
    private ?EloquentCollection $stages = null;

    public function __construct(
        private readonly Request $request,
        private readonly FlavourContextService $flavourContext,
        private readonly StageContextService $stageContext,
    ) {}

    /**
     * @return Collection<int, FlavourData>
     */
    public function flavours(): Collection
    {
        return $this->flavours ??= $this->flavourContext
            ->availableFlavours();
    }

    /**
     * @return FlavourData
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

        $routeFlavour = $this->request->route('flavour');

        $requestedCode = is_string($routeFlavour)
            ? trim($routeFlavour)
            : '';

        if ($requestedCode === '') {
            $requestedCode = (string) $this->request
                ->session()
                ->get('perfect10.last_flavour', '');
        }

        $selected = $this->flavourContext
            ->selectFlavour(
                $flavours,
                $requestedCode
            );

        if (
            is_string($routeFlavour) &&
            strtoupper(trim($routeFlavour)) ===
                strtoupper($selected['flavour_code'])
        ) {
            $this->request->session()->put(
                'perfect10.last_flavour',
                $selected['flavour_code']
            );
        }

        return $this->selectedFlavourData = $selected;
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

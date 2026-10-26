<?php

namespace App\Services\Perfect10;

use App\Models\Perfect10\Campaign;
use App\Models\Perfect10\Competition;
use App\Models\Perfect10\Flavour;
use App\Models\Perfect10\Gameweek;
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

    private bool $activeGameweekResolved = false;

    private ?Gameweek $activeGameweek = null;

    /**
     * @var EloquentCollection<int, Stage>|null
     */
    private ?EloquentCollection $stages = null;

    /**
     * @var EloquentCollection<int, Gameweek>|null
     */
    private ?EloquentCollection $gameweeks = null;

    /**
     * @var list<array<string, mixed>>|null
     */
    private ?array $fixtures = null;

    /**
     * @var array<int, list<array<string, mixed>>>
     */
    private array $stageFixtures = [];

    public function __construct(
        private readonly Request $request,
        private readonly FlavourContextService $flavourContext,
        private readonly StageContextService $stageContext,
        private readonly GameweekContextService $gameweekContext,
        private readonly FixtureContextService $fixtureContext,
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

    /**
     * @return EloquentCollection<int, Gameweek>
     */
    public function gameweeks(): EloquentCollection
    {
        return $this->gameweeks ??= $this->gameweekContext
            ->gameweeksForCampaign($this->campaign());
    }

    /**
     * Resolve the active gameweek once per request.
     */
    public function activeGameweek(): ?Gameweek
    {
        if (! $this->activeGameweekResolved) {
            $this->activeGameweek = $this->gameweekContext
                ->activeGameweek($this->gameweeks());

            $this->activeGameweekResolved = true;
        }

        return $this->activeGameweek;
    }

    /**
     * Retrieve the selected flavour's fixture-provider data.
     *
     * Results are cached for the current request.
     *
     * @return list<array<string, mixed>>
     */
    public function fixtures(): array
    {
        return $this->fixtures ??= $this->fixtureContext
            ->fixturesForFlavour($this->flavour());
    }

    /**
     * Retrieve fixtures belonging to a stage.
     *
     * Includes non-gameweek stages, such as rearranged fixtures.
     *
     * @return list<array<string, mixed>>
     */
    public function fixturesForStage(int $stageId): array
    {
        if (array_key_exists($stageId, $this->stageFixtures)) {
            return $this->stageFixtures[$stageId];
        }

        $fixtures = collect($this->fixtures())
            ->filter(
                fn (array $fixture): bool => $fixture['stage_id'] !== null &&
                    (int) $fixture['stage_id'] === $stageId
            )
            ->sort(
                fn (array $a, array $b): int => strcmp(
                    (string) $a['kickoff_at'],
                    (string) $b['kickoff_at']
                ) ?: (
                    (int) $a['fixture_id'] <=>
                    (int) $b['fixture_id']
                )
            )
            ->values()
            ->all();

        return $this->stageFixtures[$stageId] = array_values($fixtures);
    }
}

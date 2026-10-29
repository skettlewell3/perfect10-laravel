import { useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

import ContentBanner from '@/components/perfect10/app/ContentBanner';
import FixtureCard from '@/components/perfect10/fixtures/FixtureCard';
import FixturesFilters, {
    type FixtureFilterOption,
    type FixtureFiltersState,
    type FixtureSortPreset,
    type FixtureStatusFilter,
} from '@/components/perfect10/fixtures/FixturesFilters';

import type { FixtureTeam } from '@/components/perfect10/fixtures/FixtureCard';
import type { Fixture } from '@/types/perfect10/fixture';
import type { Perfect10PageProps } from '@/types/perfect10';
import type { PredictionsMap } from '@/types/perfect10/prediction';

type FixturesPageProps = Perfect10PageProps & {
    fixtures: Fixture[];
    teamsMap: Record<number, FixtureTeam>;
    predictionsMap: PredictionsMap;
};

const defaultFilters: FixtureFiltersState = {
    status: 'upcoming',
    stageId: null,
    groupLetter: null,
    teamId: null,
    predictionsOpenOnly: false,
};

export default function FixturesPage() {
    const { flavour, fixtures, teamsMap, predictionsMap } =
        usePage<FixturesPageProps>().props;

    const [filters, setFilters] = useState<FixtureFiltersState>(defaultFilters);

    const [sortPreset, setSortPreset] = useState<FixtureSortPreset>('default');

    const stageOptions = useMemo<FixtureFilterOption[]>(() => {
        const stages = new Map<number, string>();

        for (const fixture of fixtures) {
            if (!stages.has(fixture.stage_id)) {
                stages.set(fixture.stage_id, fixture.stage_name);
            }
        }

        return [...stages.entries()]
            .map(([value, label]) => ({ value, label }))
            .sort((a, b) => a.value - b.value);
    }, [fixtures]);

    const groupOptions = useMemo<FixtureFilterOption[]>(() => {
        const groups = new Set<string>();

        for (const fixture of fixtures) {
            if (fixture.group_letter) {
                groups.add(fixture.group_letter);
            }
        }

        return [...groups].sort().map((group) => ({
            value: group,
            label: `Group ${group}`,
        }));
    }, [fixtures]);

    /*
     * Team options use full team names.
     *
     * The short codes for filter chips are resolved
     * separately in FixturesFilters.
     */
    const teamOptions = useMemo<FixtureFilterOption[]>(() => {
        const availableTeams = new Map<number, string>();

        for (const fixture of fixtures) {
            const statusMatches =
                filters.status === 'all' ||
                (filters.status === 'live'
                    ? fixture.fixture_status === 'live_90' ||
                      fixture.fixture_status === 'live_et'
                    : fixture.fixture_status === filters.status);

            if (!statusMatches) continue;

            if (
                filters.stageId !== null &&
                fixture.stage_id !== filters.stageId
            ) {
                continue;
            }

            if (
                filters.groupLetter !== null &&
                fixture.group_letter !== filters.groupLetter
            ) {
                continue;
            }

            if (fixture.home_team_id !== null) {
                availableTeams.set(
                    fixture.home_team_id,
                    teamsMap[fixture.home_team_id]?.team_name ??
                        fixture.home_team_name ??
                        `Team ${fixture.home_team_id}`,
                );
            }

            if (fixture.away_team_id !== null) {
                availableTeams.set(
                    fixture.away_team_id,
                    teamsMap[fixture.away_team_id]?.team_name ??
                        fixture.away_team_name ??
                        `Team ${fixture.away_team_id}`,
                );
            }
        }

        return [...availableTeams.entries()]
            .map(([value, label]) => ({ value, label }))
            .sort((a, b) => a.label.localeCompare(b.label));
    }, [
        fixtures,
        filters.status,
        filters.stageId,
        filters.groupLetter,
        teamsMap,
    ]);

    const filteredFixtures = useMemo(() => {
        const matching = fixtures.filter((fixture) => {
            if (filters.status !== 'all') {
                if (filters.status === 'live') {
                    if (
                        fixture.fixture_status !== 'live_90' &&
                        fixture.fixture_status !== 'live_et'
                    ) {
                        return false;
                    }
                } else if (fixture.fixture_status !== filters.status) {
                    return false;
                }
            }

            if (
                filters.stageId !== null &&
                fixture.stage_id !== filters.stageId
            ) {
                return false;
            }

            if (
                filters.groupLetter !== null &&
                fixture.group_letter !== filters.groupLetter
            ) {
                return false;
            }

            if (
                filters.teamId !== null &&
                fixture.home_team_id !== filters.teamId &&
                fixture.away_team_id !== filters.teamId
            ) {
                return false;
            }

            if (filters.predictionsOpenOnly && !fixture.predictions_open) {
                return false;
            }

            return true;
        });

        const direction =
            sortPreset === 'default'
                ? filters.status === 'finished'
                    ? 'desc'
                    : 'asc'
                : sortPreset;

        matching.sort((a, b) => {
            const dateComparison =
                new Date(a.kickoff_at).getTime() -
                new Date(b.kickoff_at).getTime();

            const comparison = dateComparison || a.fixture_id - b.fixture_id;

            return direction === 'asc' ? comparison : -comparison;
        });

        return matching;
    }, [fixtures, filters, sortPreset]);

    const setStatusFilter = (status: FixtureStatusFilter) => {
        setFilters((previous) => ({ ...previous, status }));
        setSortPreset('default');
    };

    const setStageFilter = (stageId: number | null) => {
        setFilters((previous) => ({
            ...previous,
            stageId,
            status:
                previous.stageId === null &&
                stageId !== null &&
                previous.status === 'upcoming'
                    ? 'all'
                    : previous.status,
        }));
    };

    const setGroupFilter = (groupLetter: string | null) => {
        setFilters((previous) => ({
            ...previous,
            groupLetter,
            status:
                previous.groupLetter === null &&
                groupLetter !== null &&
                previous.status === 'upcoming'
                    ? 'all'
                    : previous.status,
        }));
    };

    const setTeamFilter = (teamId: number | null) => {
        setFilters((previous) => ({
            ...previous,
            teamId,
            groupLetter: teamId !== null ? null : previous.groupLetter,
            status:
                previous.teamId === null &&
                teamId !== null &&
                previous.status === 'upcoming'
                    ? 'all'
                    : previous.status,
        }));
    };

    const resetFilters = () => {
        setFilters({ ...defaultFilters });
        setSortPreset('default');
    };

    return (
        <>
            <Head title="Fixtures" />

            <div className="pageShell">
                <ContentBanner title="Fixtures" />

                <FixturesFilters
                    fixtures={fixtures}
                    filters={filters}
                    sortPreset={sortPreset}
                    stages={stageOptions}
                    groups={groupOptions}
                    teams={teamOptions}
                    onStatusChange={setStatusFilter}
                    onStageChange={setStageFilter}
                    onGroupChange={setGroupFilter}
                    onTeamChange={setTeamFilter}
                    onPredictionsToggle={() =>
                        setFilters((previous) => ({
                            ...previous,
                            predictionsOpenOnly: !previous.predictionsOpenOnly,
                        }))
                    }
                    onSortChange={setSortPreset}
                    onReset={resetFilters}
                />

                <div className="scrollArea fixturesList">
                    {filteredFixtures.map((fixture) => (
                        <FixtureCard
                            key={fixture.fixture_id}
                            fixture={fixture}
                            formatCode={flavour.format_code}
                            flavourCode={flavour.flavour_code}
                            teamsMap={teamsMap}
                            prediction={
                                predictionsMap[fixture.fixture_id] ?? null
                            }
                        />
                    ))}

                    {filteredFixtures.length === 0 && (
                        <p>No fixtures match the selected filters.</p>
                    )}
                </div>
            </div>
        </>
    );
}

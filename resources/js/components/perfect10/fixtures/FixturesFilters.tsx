import { useMemo, useState } from 'react';

import FixtureFilterSelect from './FixtureFilterSelect';

import type { Fixture } from '@/types/perfect10/fixture';

export type FixtureStatusFilter = 'upcoming' | 'live' | 'finished' | 'all';
export type FixtureSortPreset = 'default' | 'asc' | 'desc';

export type FixtureFiltersState = {
    status: FixtureStatusFilter;
    stageId: number | null;
    groupLetter: string | null;
    teamId: number | null;
    predictionsOpenOnly: boolean;
};

export type FixtureFilterOption = {
    value: number | string;
    label: string;
};

type Props = {
    fixtures: Fixture[];
    filters: FixtureFiltersState;
    sortPreset: FixtureSortPreset;
    stages: FixtureFilterOption[];
    groups: FixtureFilterOption[];
    teams: FixtureFilterOption[];
    onStatusChange: (status: FixtureStatusFilter) => void;
    onStageChange: (stageId: number | null) => void;
    onGroupChange: (groupLetter: string | null) => void;
    onTeamChange: (teamId: number | null) => void;
    onPredictionsToggle: () => void;
    onSortChange: (sort: FixtureSortPreset) => void;
    onReset: () => void;
};

const statusOptions: {
    value: FixtureStatusFilter;
    label: string;
}[] = [
    { value: 'upcoming', label: 'Upcoming' },
    { value: 'live', label: 'Live' },
    { value: 'finished', label: 'Finished' },
    { value: 'all', label: 'All' },
];

export default function FixturesFilters({
    fixtures,
    filters,
    sortPreset,
    stages,
    groups,
    teams,
    onStatusChange,
    onStageChange,
    onGroupChange,
    onTeamChange,
    onPredictionsToggle,
    onSortChange,
    onReset,
}: Props) {
    const [expanded, setExpanded] = useState(false);

    const selectedStage = stages.find(
        (stage) => stage.value === filters.stageId,
    );

    const selectedTeam = teams.find((team) => team.value === filters.teamId);

    /*
     * Resolve the selected team's three-character code
     * from the fixture provider data.
     *
     * The dropdown continues to use the full team name.
     */
    const selectedTeamCode = useMemo(() => {
        if (filters.teamId === null) {
            return null;
        }

        for (const fixture of fixtures) {
            if (fixture.home_team_id === filters.teamId) {
                return fixture.home_short_code;
            }

            if (fixture.away_team_id === filters.teamId) {
                return fixture.away_short_code;
            }
        }

        return null;
    }, [fixtures, filters.teamId]);

    const hasActiveFilters =
        filters.stageId !== null ||
        filters.groupLetter !== null ||
        filters.teamId !== null ||
        filters.predictionsOpenOnly;

    const showGroupFilter = groups.length > 0;

    return (
        <div className="filters fixtureFilters">
            <div className="filterFixedRow">
                <div className="fixStatusFilter">
                    {statusOptions.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            className={
                                filters.status === option.value ? 'active' : ''
                            }
                            onClick={() => onStatusChange(option.value)}
                            aria-pressed={filters.status === option.value}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
            </div>

            {expanded && (
                <>
                    <div className="filterRow">
                        <FixtureFilterSelect
                            value={filters.stageId}
                            options={stages}
                            placeholder="All Stages"
                            active={filters.stageId !== null}
                            onChange={(value) =>
                                onStageChange(
                                    value === null ? null : Number(value),
                                )
                            }
                        />

                        {showGroupFilter && (
                            <FixtureFilterSelect
                                value={filters.groupLetter}
                                options={groups}
                                placeholder="All Groups"
                                active={filters.groupLetter !== null}
                                disabled={filters.teamId !== null}
                                onChange={(value) =>
                                    onGroupChange(
                                        value === null ? null : String(value),
                                    )
                                }
                            />
                        )}
                    </div>

                    <div className="filterRow">
                        <FixtureFilterSelect
                            value={filters.teamId}
                            options={teams}
                            placeholder="All Teams"
                            active={filters.teamId !== null}
                            onChange={(value) =>
                                onTeamChange(
                                    value === null ? null : Number(value),
                                )
                            }
                        />

                        <div
                            className={`predictionFilterContainer ${
                                filters.predictionsOpenOnly ? 'active' : ''
                            }`}
                        >
                            <button
                                type="button"
                                className={`predictionsOpenFilter ${
                                    filters.predictionsOpenOnly ? 'active' : ''
                                }`}
                                aria-pressed={filters.predictionsOpenOnly}
                                onClick={onPredictionsToggle}
                            >
                                {filters.predictionsOpenOnly
                                    ? 'Predictions Open'
                                    : 'All Matches'}
                            </button>
                        </div>
                    </div>
                </>
            )}

            <div className="fixtureFiltersFooter">
                <div className="activeFilters">
                    {hasActiveFilters && (
                        <div className="activeFiltersRow">
                            <div className="clearFilterChips">
                                <button type="button" onClick={onReset}>
                                    Clear All
                                </button>
                            </div>

                            {filters.stageId !== null && (
                                <div className="filterChip">
                                    <div className="chipLabels">
                                        <span>
                                            {selectedStage?.label ??
                                                `Stage ${filters.stageId}`}
                                        </span>
                                    </div>

                                    <div className="chipX">
                                        <button
                                            type="button"
                                            className="chipXButton"
                                            aria-label="Remove stage filter"
                                            onClick={() => onStageChange(null)}
                                        >
                                            ×
                                        </button>
                                    </div>
                                </div>
                            )}

                            {filters.groupLetter !== null && (
                                <div className="filterChip">
                                    <div className="chipLabels">
                                        <span>Group {filters.groupLetter}</span>
                                    </div>

                                    <div className="chipX">
                                        <button
                                            type="button"
                                            className="chipXButton"
                                            aria-label="Remove group filter"
                                            onClick={() => onGroupChange(null)}
                                        >
                                            ×
                                        </button>
                                    </div>
                                </div>
                            )}

                            {filters.teamId !== null && (
                                <div className="filterChip">
                                    <div className="chipLabels">
                                        <span>
                                            {selectedTeamCode ??
                                                selectedTeam?.label ??
                                                `Team ${filters.teamId}`}
                                        </span>
                                    </div>

                                    <div className="chipX">
                                        <button
                                            type="button"
                                            className="chipXButton"
                                            aria-label="Remove team filter"
                                            onClick={() => onTeamChange(null)}
                                        >
                                            ×
                                        </button>
                                    </div>
                                </div>
                            )}

                            {filters.predictionsOpenOnly && (
                                <div className="filterChip">
                                    <div className="chipLabels">
                                        <span>Predictions Open</span>
                                    </div>

                                    <div className="chipX">
                                        <button
                                            type="button"
                                            className="chipXButton"
                                            aria-label="Remove predictions filter"
                                            onClick={onPredictionsToggle}
                                        >
                                            ×
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    <div className="fixtureSorting">
                        <button
                            type="button"
                            className={`sortButton ${
                                sortPreset === 'asc' ? 'active' : ''
                            }`}
                            onClick={() =>
                                onSortChange(
                                    sortPreset === 'asc' ? 'default' : 'asc',
                                )
                            }
                            aria-label="Sort by earliest kickoff"
                            aria-pressed={sortPreset === 'asc'}
                        >
                            <img src="/assets/svg/sortBtoT.svg" alt="" />
                        </button>

                        <button
                            type="button"
                            className={`sortButton ${
                                sortPreset === 'desc' ? 'active' : ''
                            }`}
                            onClick={() =>
                                onSortChange(
                                    sortPreset === 'desc' ? 'default' : 'desc',
                                )
                            }
                            aria-label="Sort by latest kickoff"
                            aria-pressed={sortPreset === 'desc'}
                        >
                            <img src="/assets/svg/sortTtoB.svg" alt="" />
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    className="filterExpandToggle"
                    onClick={() => setExpanded((previous) => !previous)}
                    aria-expanded={expanded}
                >
                    {expanded ? 'collapse' : 'expand'}
                </button>
            </div>
        </div>
    );
}

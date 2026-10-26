import { useState } from 'react';

import TeamComparisonHeader from './TeamComparisonHeader';
import TeamComparisonRow from './TeamComparisonRow';
import TeamComparisonSummary from './TeamComparisonSummary';
import TeamComparisonToggle from './TeamComparisonToggle';

import type { FixtureTeam } from '@/components/perfect10/fixtures/FixtureCard';
import type {
    TeamComparisonData,
    TeamComparisonMode,
    TeamComparisonTeams,
} from '@/types/perfect10/team-comparison';

type Props = {
    data: TeamComparisonData;
    teams: TeamComparisonTeams;
    teamsMap: Record<number, FixtureTeam>;
    team1ShortCode: string | null;
    team2ShortCode: string | null;
};

export default function TeamComparisonCore({
    data,
    teams,
    teamsMap,
    team1ShortCode,
    team2ShortCode,
}: Props) {
    const [comparisonMode, setComparisonMode] =
        useState<TeamComparisonMode>('generic');

    if (data.length === 0) {
        return <p>No comparison data available.</p>;
    }

    return (
        <div className="teamComparisonCore">
            <TeamComparisonToggle
                comparisonMode={comparisonMode}
                onModeChange={setComparisonMode}
            />

            <div className="comparisonGrid">
                <TeamComparisonSummary
                    data={data}
                    comparisonMode={comparisonMode}
                />

                <TeamComparisonHeader
                    comparisonMode={comparisonMode}
                    teams={teams}
                    teamsMap={teamsMap}
                    team1ShortCode={team1ShortCode}
                    team2ShortCode={team2ShortCode}
                />

                {data.map((row) => (
                    <TeamComparisonRow
                        key={row.opponent_id}
                        rowData={row}
                        viewMode={comparisonMode}
                        teamsMap={teamsMap}
                    />
                ))}
            </div>
        </div>
    );
}

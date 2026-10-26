import type { CSSProperties } from 'react';

import FixtureCellHeader from './FixtureCellHeader';

import type { FixtureTeam } from '@/components/perfect10/fixtures/FixtureCard';
import type {
    TeamComparisonMode,
    TeamComparisonTeams,
} from '@/types/perfect10/team-comparison';

type Props = {
    comparisonMode: TeamComparisonMode;
    teams: TeamComparisonTeams;
    teamsMap: Record<number, FixtureTeam>;
    team1ShortCode: string | null;
    team2ShortCode: string | null;
};

function getTeamStyle(team?: FixtureTeam): CSSProperties {
    if (!team) return {};

    return {
        backgroundColor: team.color_1 ?? undefined,
        color: team.color_2 ?? undefined,
        textShadow: team.color_3
            ? `-0.5px -0.5px 0 ${team.color_3},
                0.5px -0.5px 0 ${team.color_3},
               -0.5px 0.5px 0 ${team.color_3},
                0.5px 0.5px 0 ${team.color_3}`
            : undefined,
    };
}

export default function TeamComparisonHeader({
    comparisonMode,
    teams,
    teamsMap,
    team1ShortCode,
    team2ShortCode,
}: Props) {
    const team1 = teamsMap[teams.team1];
    const team2 = teamsMap[teams.team2];

    const totalHeader = comparisonMode === 'generic' ? 'Pts' : '+/-';
    const metaHeader = comparisonMode === 'generic' ? '(Pts)' : '+/-';

    return (
        <div className="teamComparisonHeader">
            <div className="comparisonHeaderTotal t1TotalHeader">
                {totalHeader}
            </div>

            <div className="t1Header" style={getTeamStyle(team1)}>
                {team1ShortCode ?? team1?.team_name ?? ''}
            </div>

            <FixtureCellHeader
                venue="H"
                metaHeader={metaHeader}
                className="t1HomeHeader"
            />

            <FixtureCellHeader
                venue="A"
                metaHeader={metaHeader}
                className="t1AwayHeader"
            />

            <div className="oppoHeader">Vs</div>

            <div className="t2Header" style={getTeamStyle(team2)}>
                {team2ShortCode ?? team2?.team_name ?? ''}
            </div>

            <FixtureCellHeader
                venue="H"
                metaHeader={metaHeader}
                className="t2HomeHeader"
            />

            <FixtureCellHeader
                venue="A"
                metaHeader={metaHeader}
                className="t2AwayHeader"
            />

            <div className="comparisonHeaderTotal t2TotalHeader">
                {totalHeader}
            </div>
        </div>
    );
}

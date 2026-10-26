import type { CSSProperties } from 'react';

import FixtureCell from './FixtureCell';

import type { FixtureTeam } from '@/components/perfect10/fixtures/FixtureCard';
import type {
    TeamComparisonMode,
    TeamComparisonRowData,
} from '@/types/perfect10/team-comparison';

type Props = {
    rowData: TeamComparisonRowData;
    viewMode: TeamComparisonMode;
    teamsMap: Record<number, FixtureTeam>;
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

function getTotalStyle(
    ownTotal: number | null,
    otherTotal: number | null,
): CSSProperties {
    if (ownTotal === null || otherTotal === null) {
        return { backgroundColor: 'transparent' };
    }

    return {
        backgroundColor:
            ownTotal > otherTotal
                ? 'rgb(32, 100, 60)'
                : ownTotal < otherTotal
                  ? '#e70f0f'
                  : '#dbbc0f',
    };
}

export default function TeamComparisonRow({
    rowData,
    viewMode,
    teamsMap,
}: Props) {
    const direct = viewMode === 'direct';

    const t1Total = direct ? rowData.t1_total_swing : rowData.t1_total_points;

    const t2Total = direct ? rowData.t2_total_swing : rowData.t2_total_points;

    const opponent = teamsMap[rowData.opponent_id];

    return (
        <div className="teamComparisonRow">
            <div
                className="comparisonRowTotal t1"
                style={getTotalStyle(t1Total, t2Total)}
            >
                {t1Total ?? ''}
            </div>

            <FixtureCell
                hGoals={rowData.t1_home_gf}
                aGoals={rowData.t1_home_ga}
                metaData={direct ? rowData.t1_home_swing : rowData.t1_home_pts}
                className="t1Home"
            />

            <FixtureCell
                hGoals={rowData.t1_away_gf}
                aGoals={rowData.t1_away_ga}
                metaData={direct ? rowData.t1_away_swing : rowData.t1_away_pts}
                className="t1Away"
            />

            <div
                className="comparisonRowOppo"
                style={getTeamStyle(opponent)}
                title={rowData.opponent_name}
            >
                {rowData.opponent_short ?? rowData.opponent_name}
            </div>

            <FixtureCell
                hGoals={rowData.t2_home_gf}
                aGoals={rowData.t2_home_ga}
                metaData={direct ? rowData.t2_home_swing : rowData.t2_home_pts}
                className="t2Home"
            />

            <FixtureCell
                hGoals={rowData.t2_away_gf}
                aGoals={rowData.t2_away_ga}
                metaData={direct ? rowData.t2_away_swing : rowData.t2_away_pts}
                className="t2Away"
            />

            <div
                className="comparisonRowTotal t2"
                style={getTotalStyle(t2Total, t1Total)}
            >
                {t2Total ?? ''}
            </div>
        </div>
    );
}

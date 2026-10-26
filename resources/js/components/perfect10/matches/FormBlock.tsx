import type { CSSProperties } from 'react';

import type { FixtureTeam } from '@/components/perfect10/fixtures/FixtureCard';
import type { Fixture } from '@/types/perfect10/fixture';

type FormBlockProps = {
    side: 'left' | 'right';
    fixture: Fixture | null;
    teamId: number;
    scale: number;
    teamsMap: Record<number, FixtureTeam>;
};

function getTeamStyle(team?: FixtureTeam): CSSProperties {
    if (!team) return {};

    const style: CSSProperties = {};

    if (team.color_1) {
        style.backgroundColor = team.color_1;
    }

    if (team.color_2) {
        style.color = team.color_2;
    }

    if (team.color_3) {
        style.textShadow = `
            -0.5px -0.5px 0 ${team.color_3},
             0.5px -0.5px 0 ${team.color_3},
            -0.5px  0.5px 0 ${team.color_3},
             0.5px  0.5px 0 ${team.color_3}
        `;
    }

    return style;
}

function getResult(
    goalsFor: number | null,
    goalsAgainst: number | null,
): 'W' | 'D' | 'L' | '' {
    if (goalsFor === null || goalsAgainst === null) {
        return '';
    }

    if (goalsFor > goalsAgainst) return 'W';
    if (goalsFor < goalsAgainst) return 'L';

    return 'D';
}

export default function FormBlock({
    side,
    fixture,
    teamId,
    scale,
    teamsMap,
}: FormBlockProps) {
    if (!fixture) return null;

    const isHome = fixture.home_team_id === teamId;

    const opponentId = isHome ? fixture.away_team_id : fixture.home_team_id;

    const opponent = isHome ? fixture.away_short_code : fixture.home_short_code;

    const opponentTeam = opponentId !== null ? teamsMap[opponentId] : undefined;

    const venue = isHome ? 'H' : 'A';

    const goalsFor = isHome
        ? fixture.final_home_goals
        : fixture.final_away_goals;

    const goalsAgainst = isHome
        ? fixture.final_away_goals
        : fixture.final_home_goals;

    const result = getResult(goalsFor, goalsAgainst);

    const kickoff = new Date(fixture.kickoff_at);

    const date = kickoff.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
    });

    const ko = kickoff.toLocaleTimeString('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
    });

    return (
        <div
            className={`formBlock ${side}`}
            style={{ fontSize: `${scale}rem` }}
        >
            <div className="dateColFG">
                <div>{date}</div>
                <div>{ko}</div>
            </div>

            <div className="venueFG">{venue}</div>

            <div
                className="oppoFG"
                style={getTeamStyle(opponentTeam)}
                title={opponentTeam?.team_name ?? undefined}
            >
                {opponent}
            </div>

            <div className="resultColFG">
                <span>{goalsFor ?? ''}</span>
                <span>-</span>
                <span>{goalsAgainst ?? ''}</span>
            </div>

            <div className="resultColFG">
                <div className={`resultIconFG ${result}`}>{result}</div>
            </div>
        </div>
    );
}

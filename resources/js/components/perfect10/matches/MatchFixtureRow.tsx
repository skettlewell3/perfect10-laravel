import type { CSSProperties } from 'react';

import type { FixtureTeam } from '@/components/perfect10/fixtures/FixtureCard';
import type { LeagueTableRowData } from '@/components/perfect10/tables/LeagueTable';
import type { Fixture } from '@/types/perfect10/fixture';
import type {
    FixturePrediction,
    PredictionDraft,
} from '@/types/perfect10/prediction';

type MatchFixtureRowProps = {
    fixture: Fixture;
    prediction?: FixturePrediction | null;
    draft?: PredictionDraft | null;
    teamsMap: Record<number, FixtureTeam>;
    actualTable: LeagueTableRowData[];
    predictionWindowOpen: boolean;
    onDraftChange?: (draft: PredictionDraft) => void;
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

function ordinalSuffix(position: number): string {
    const lastTwo = position % 100;

    if (lastTwo >= 11 && lastTwo <= 13) return 'th';

    switch (position % 10) {
        case 1:
            return 'st';
        case 2:
            return 'nd';
        case 3:
            return 'rd';
        default:
            return 'th';
    }
}

function TeamPosition({ position }: { position: number | null }) {
    return (
        <div className="teamPos">
            {position !== null && (
                <span>
                    {position}
                    <sup>{ordinalSuffix(position)}</sup>
                </span>
            )}
        </div>
    );
}

export default function MatchFixtureRow({
    fixture,
    prediction,
    draft,
    teamsMap,
    actualTable,
    predictionWindowOpen,
    onDraftChange,
}: MatchFixtureRowProps) {
    const homeTeam =
        fixture.home_team_id !== null
            ? teamsMap[fixture.home_team_id]
            : undefined;

    const awayTeam =
        fixture.away_team_id !== null
            ? teamsMap[fixture.away_team_id]
            : undefined;

    const homePosition =
        actualTable.find((row) => row.team_id === fixture.home_team_id)?.pos ??
        null;

    const awayPosition =
        actualTable.find((row) => row.team_id === fixture.away_team_id)?.pos ??
        null;

    const hasStarted =
        fixture.fixture_status === 'live_90' ||
        fixture.fixture_status === 'live_et' ||
        fixture.fixture_status === 'finished';

    const isEditable =
        fixture.fixture_status === 'upcoming' &&
        fixture.predictions_open &&
        predictionWindowOpen &&
        Boolean(onDraftChange);

    const homeValue =
        draft?.home ?? prediction?.pred_home_goals?.toString() ?? '';

    const awayValue =
        draft?.away ?? prediction?.pred_away_goals?.toString() ?? '';

    const homeScore = hasStarted
        ? (fixture.final_home_goals ?? fixture.ft_home_goals)
        : null;

    const awayScore = hasStarted
        ? (fixture.final_away_goals ?? fixture.ft_away_goals)
        : null;

    const handleChange = (side: 'home' | 'away', value: string) => {
        if (!onDraftChange) return;

        if (value !== '' && !/^[0-9]$/.test(value)) return;

        onDraftChange({
            home: side === 'home' ? value : homeValue,
            away: side === 'away' ? value : awayValue,
        });
    };

    const displayedHome = hasStarted
        ? (homeScore ?? '–')
        : (prediction?.pred_home_goals ?? '–');

    const displayedAway = hasStarted
        ? (awayScore ?? '–')
        : (prediction?.pred_away_goals ?? '–');

    return (
        <fieldset className="modalFieldset match">
            <div
                className="posTeam"
                style={getTeamStyle(homeTeam)}
                title={homeTeam?.team_name ?? fixture.home_team_name ?? ''}
            >
                <TeamPosition position={homePosition} />

                <div className="posName">
                    {fixture.home_short_code ?? 'TBC'}
                </div>
            </div>

            <div className="homeScore">
                {isEditable ? (
                    <input
                        type="text"
                        inputMode="numeric"
                        pattern="[0-9]"
                        maxLength={1}
                        name={`home_${fixture.fixture_id}`}
                        className="pred"
                        value={homeValue}
                        onChange={(event) =>
                            handleChange('home', event.target.value)
                        }
                        aria-label={`${fixture.home_team_name ?? 'Home'} predicted goals`}
                    />
                ) : (
                    displayedHome
                )}
            </div>

            <div className="v">v</div>

            <div className="awayScore">
                {isEditable ? (
                    <input
                        type="text"
                        inputMode="numeric"
                        pattern="[0-9]"
                        maxLength={1}
                        name={`away_${fixture.fixture_id}`}
                        className="pred"
                        value={awayValue}
                        onChange={(event) =>
                            handleChange('away', event.target.value)
                        }
                        aria-label={`${fixture.away_team_name ?? 'Away'} predicted goals`}
                    />
                ) : (
                    displayedAway
                )}
            </div>

            <div
                className="posTeam"
                style={getTeamStyle(awayTeam)}
                title={awayTeam?.team_name ?? fixture.away_team_name ?? ''}
            >
                <div className="posName">
                    {fixture.away_short_code ?? 'TBC'}
                </div>

                <TeamPosition position={awayPosition} />
            </div>
        </fieldset>
    );
}

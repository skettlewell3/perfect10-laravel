import { useState } from 'react';
import type { CSSProperties } from 'react';
import { router } from '@inertiajs/react';

import type { Fixture } from '@/types/perfect10/fixture';
import type {
    FixturePrediction,
    PredictionDraft,
} from '@/types/perfect10/prediction';
import type { FixtureTeam } from './FixtureCard';

import FixtureExpandButton from './FixtureExpandButton';
import FixtureScoreBreakdown from './FixtureScoreBreakdown';

type StageFixtureRowProps = {
    fixture: Fixture;
    prediction?: FixturePrediction | null;
    draft?: PredictionDraft | null;
    teamsMap: Record<number, FixtureTeam>;
    predictionWindowOpen: boolean;
    onDraftChange?: (draft: PredictionDraft) => void;
    flavourCode: string;
    showMatchDetailsButton?: boolean;
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

const matchDetailsButtonStyle: CSSProperties = {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    width: '100%',
    minWidth: 0,
    padding: 0,
    margin: 0,
    border: 'none',
    background: 'transparent',
    color: '#fff',
    font: 'inherit',
    lineHeight: 'inherit',
    cursor: 'pointer',
};

export default function StageFixtureRow({
    fixture,
    prediction,
    draft,
    teamsMap,
    predictionWindowOpen,
    onDraftChange,
    flavourCode,
    showMatchDetailsButton = true,
}: StageFixtureRowProps) {
    const hasScoring = prediction?.points_total != null;

    const [expanded, setExpanded] = useState(hasScoring);

    const homeTeam =
        fixture.home_team_id !== null
            ? teamsMap[fixture.home_team_id]
            : undefined;

    const awayTeam =
        fixture.away_team_id !== null
            ? teamsMap[fixture.away_team_id]
            : undefined;

    const homeValue =
        draft?.home ?? prediction?.pred_home_goals?.toString() ?? '';

    const awayValue =
        draft?.away ?? prediction?.pred_away_goals?.toString() ?? '';

    const isEditable =
        fixture.fixture_status === 'upcoming' &&
        fixture.predictions_open &&
        predictionWindowOpen &&
        Boolean(onDraftChange);

    const hasStarted =
        fixture.fixture_status === 'live_90' ||
        fixture.fixture_status === 'live_et' ||
        fixture.fixture_status === 'finished';

    const homeScore = hasStarted
        ? (fixture.final_home_goals ?? fixture.ft_home_goals)
        : null;

    const awayScore = hasStarted
        ? (fixture.final_away_goals ?? fixture.ft_away_goals)
        : null;

    const showPrediction = !isEditable && !hasStarted && Boolean(prediction);

    const handleChange = (side: 'home' | 'away', value: string) => {
        if (!onDraftChange) return;

        if (value !== '' && !/^[0-9]$/.test(value)) {
            return;
        }

        onDraftChange({
            home: side === 'home' ? value : homeValue,
            away: side === 'away' ? value : awayValue,
        });
    };

    const openMatchDetails = () => {
        router.get(
            `/${flavourCode.trim().toLowerCase()}/matches/${fixture.fixture_id}`,
        );
    };

    return (
        <div className="fixtureFieldsetWrapper">
            <div className="stageFixtureRow">
                {showMatchDetailsButton ? (
                    <button
                        type="button"
                        className="matchModalButton"
                        style={matchDetailsButtonStyle}
                        onClick={(event) => {
                            event.stopPropagation();
                            openMatchDetails();
                        }}
                        aria-label={`View ${fixture.home_team_name ?? 'Home'} versus ${fixture.away_team_name ?? 'Away'} match details`}
                    >
                        ⓘ
                    </button>
                ) : (
                    <div aria-hidden="true" />
                )}

                <div className="team home" style={getTeamStyle(homeTeam)}>
                    {fixture.home_short_code ?? 'TBC'}
                </div>

                <div className="scoreCell">
                    {isEditable ? (
                        <div className="homePred">
                            <input
                                type="text"
                                inputMode="numeric"
                                pattern="[0-9]"
                                maxLength={1}
                                className="predInput"
                                value={homeValue}
                                onChange={(event) =>
                                    handleChange('home', event.target.value)
                                }
                                aria-label={`${fixture.home_team_name ?? 'Home'} predicted goals`}
                            />
                        </div>
                    ) : hasStarted ? (
                        <div className="homeScore">{homeScore ?? '–'}</div>
                    ) : showPrediction ? (
                        <div className="homePred">
                            {prediction?.pred_home_goals}
                        </div>
                    ) : (
                        <div />
                    )}
                </div>

                <div className="vs">v</div>

                <div className="scoreCell">
                    {isEditable ? (
                        <div className="awayPred">
                            <input
                                type="text"
                                inputMode="numeric"
                                pattern="[0-9]"
                                maxLength={1}
                                className="predInput"
                                value={awayValue}
                                onChange={(event) =>
                                    handleChange('away', event.target.value)
                                }
                                aria-label={`${fixture.away_team_name ?? 'Away'} predicted goals`}
                            />
                        </div>
                    ) : hasStarted ? (
                        <div className="awayScore">{awayScore ?? '–'}</div>
                    ) : showPrediction ? (
                        <div className="awayPredLoc">
                            {prediction?.pred_away_goals}
                        </div>
                    ) : (
                        <div />
                    )}
                </div>

                <div className="team away" style={getTeamStyle(awayTeam)}>
                    {fixture.away_short_code ?? 'TBC'}
                </div>

                <div>
                    {hasScoring && (
                        <FixtureExpandButton
                            isExpanded={expanded}
                            onClick={() => setExpanded((previous) => !previous)}
                        />
                    )}
                </div>
            </div>

            {expanded && hasScoring && (
                <FixtureScoreBreakdown prediction={prediction} />
            )}
        </div>
    );
}

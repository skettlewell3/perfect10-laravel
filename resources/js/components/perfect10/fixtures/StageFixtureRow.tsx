import { useState } from 'react';
import type { CSSProperties } from 'react';

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

export default function StageFixtureRow({
    fixture,
    prediction,
    draft,
    teamsMap,
    predictionWindowOpen,
    onDraftChange,
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

        // Allow clearing the input or entering one digit from 0–9.
        if (value !== '' && !/^[0-9]$/.test(value)) {
            return;
        }

        onDraftChange({
            home: side === 'home' ? value : homeValue,
            away: side === 'away' ? value : awayValue,
        });
    };

    return (
        <div className="fixtureFieldsetWrapper">
            <div className="stageFixtureRow">
                {/* Match details — modal integration later */}
                <div className="matchModalButton">
                    <span aria-hidden="true">ⓘ</span>
                </div>

                {/* Home team */}
                <div className="team home" style={getTeamStyle(homeTeam)}>
                    {fixture.home_short_code ?? 'TBC'}
                </div>

                {/* Home score */}
                <div className="scoreCell">
                    {isEditable ? (
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
                    ) : (
                        <div className={hasStarted ? 'homeScore' : 'homePred'}>
                            {hasStarted
                                ? (homeScore ?? '–')
                                : showPrediction
                                  ? prediction?.pred_home_goals
                                  : '–'}
                        </div>
                    )}
                </div>

                {/* Versus */}
                <div className="vs">V</div>

                {/* Away score */}
                <div className="scoreCell">
                    {isEditable ? (
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
                    ) : (
                        <div className={hasStarted ? 'awayScore' : 'awayPred'}>
                            {hasStarted
                                ? (awayScore ?? '–')
                                : showPrediction
                                  ? prediction?.pred_away_goals
                                  : '–'}
                        </div>
                    )}
                </div>

                {/* Away team */}
                <div className="team away" style={getTeamStyle(awayTeam)}>
                    {fixture.away_short_code ?? 'TBC'}
                </div>

                {/* Score breakdown toggle */}
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

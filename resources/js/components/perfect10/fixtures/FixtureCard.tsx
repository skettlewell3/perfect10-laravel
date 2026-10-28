import { useState } from 'react';

import type { Fixture } from '@/types/perfect10/fixture';
import type {
    FixturePrediction,
    PredictionDraft,
} from '@/types/perfect10/prediction';

import useFixtureLeaderboard from '@/hooks/perfect10/useFixtureLeaderboard';
import { canShowFixtureLeaderboard } from '@/utils/perfect10/fixture-leaderboard';

import FixtureCardHeader from './FixtureCardHeader';
import FixtureDetails from './FixtureDetails';
import FixtureLeaderboard from './FixtureLeaderboard';
import FixtureLeaderboardToggle from './FixtureLeaderboardToggle';
import FixtureScorePanel from './FixtureScorePanel';
import TeamDisplay from './TeamDisplay';

export type FixtureTeam = {
    team_id: number;
    team_name: string;
    flag_code: string | null;
    color_1: string | null;
    color_2: string | null;
    color_3: string | null;
    group_letter?: string | null;
};

type FixtureCardProps = {
    fixture: Fixture;
    formatCode: string;
    flavourCode: string;
    teamsMap?: Record<number, FixtureTeam>;

    prediction?: FixturePrediction | null;
    draft?: PredictionDraft | null;
    onDraftChange?: (draft: PredictionDraft) => void;
};

export default function FixtureCard({
    fixture,
    formatCode,
    flavourCode,
    teamsMap = {},
    prediction,
    draft,
    onDraftChange,
}: FixtureCardProps) {
    const [showLeaderboard, setShowLeaderboard] = useState(false);

    const isGameweekFormat = formatCode === 'GWK';

    const isStandalone = isGameweekFormat && fixture.gameweek_id === null;

    const canEdit =
        fixture.fixture_status === 'upcoming' &&
        fixture.predictions_open &&
        (!isGameweekFormat || isStandalone);

    const leaderboardEligible = canShowFixtureLeaderboard(
        fixture.fixture_status,
    );

    const {
        data: leaderboard,
        loading: leaderboardLoading,
        error,
    } = useFixtureLeaderboard({
        fixtureId: fixture.fixture_id,
        flavourCode,
        enabled: leaderboardEligible && showLeaderboard,
    });

    const homeTeam =
        fixture.home_team_id !== null
            ? teamsMap[fixture.home_team_id]
            : undefined;

    const awayTeam =
        fixture.away_team_id !== null
            ? teamsMap[fixture.away_team_id]
            : undefined;

    const isFinished = fixture.fixture_status === 'finished';

    const homeWon =
        isFinished &&
        fixture.winner_team_id !== null &&
        fixture.winner_team_id === fixture.home_team_id;

    const awayWon =
        isFinished &&
        fixture.winner_team_id !== null &&
        fixture.winner_team_id === fixture.away_team_id;

    const showProgressionMarker = isFinished && fixture.winner_team_id !== null;

    const teamVariant = isGameweekFormat ? 'colours' : 'flag';

    const stageClass = isGameweekFormat
        ? fixture.gameweek_number !== null
            ? `gwStage-${fixture.gameweek_number % 4}`
            : 'specialStage'
        : 'perFixtureCard';

    const showPredictionStatus =
        !isGameweekFormat ||
        (fixture.gameweek_id !== null &&
            fixture.gameweek_status === 'active') ||
        isStandalone;

    const groupLetter =
        fixture.group_letter ??
        homeTeam?.group_letter ??
        awayTeam?.group_letter;

    return (
        <div className={`fixtureCard ${stageClass}`}>
            <FixtureCardHeader
                fixture={fixture}
                showPredictionStatus={showPredictionStatus}
            />

            <div className="fixtureBody">
                <div className="fCardCol L">
                    <div className="stageCell">{fixture.stage_name}</div>

                    <TeamDisplay
                        name={fixture.home_team_name}
                        variant={teamVariant}
                        flagCode={homeTeam?.flag_code}
                        primaryColor={homeTeam?.color_1}
                        secondaryColor={homeTeam?.color_2}
                        tertiaryColor={homeTeam?.color_3}
                        isWinner={homeWon}
                        isLoser={awayWon}
                        showProgressionMarker={showProgressionMarker && homeWon}
                    />

                    <div className="deadlineCell">
                        {fixture.prediction_close_at &&
                            fixture.fixture_status === 'upcoming' && (
                                <>
                                    <span className="deadlineLabel">
                                        Closes:
                                    </span>
                                    <span className="deadline">
                                        {new Intl.DateTimeFormat('en-GB', {
                                            day: '2-digit',
                                            month: '2-digit',
                                            hour: '2-digit',
                                            minute: '2-digit',
                                            hourCycle: 'h23',
                                        }).format(
                                            new Date(
                                                fixture.prediction_close_at,
                                            ),
                                        )}
                                    </span>
                                </>
                            )}
                    </div>
                </div>

                <div className="fCardDetailsCol">
                    <FixtureDetails fixture={fixture} />

                    <FixtureScorePanel
                        fixture={fixture}
                        prediction={prediction}
                        draft={draft}
                        canEdit={canEdit}
                        onDraftChange={onDraftChange}
                    />
                </div>

                <div className="fCardCol R">
                    <div className="groupCell">
                        {fixture.stage_type === 'group' &&
                            groupLetter &&
                            `Group ${groupLetter}`}
                    </div>

                    <TeamDisplay
                        name={fixture.away_team_name}
                        variant={teamVariant}
                        flagCode={awayTeam?.flag_code}
                        primaryColor={awayTeam?.color_1}
                        secondaryColor={awayTeam?.color_2}
                        tertiaryColor={awayTeam?.color_3}
                        isWinner={awayWon}
                        isLoser={homeWon}
                        showProgressionMarker={showProgressionMarker && awayWon}
                    />

                    <div className="submitCell">
                        {canEdit && (
                            <button
                                type="button"
                                className="pfSubmit noSubmit"
                                disabled
                            >
                                Submit
                            </button>
                        )}

                        {leaderboardEligible && (
                            <FixtureLeaderboardToggle
                                showLeaderboard={showLeaderboard}
                                onToggle={() =>
                                    setShowLeaderboard((previous) => !previous)
                                }
                            />
                        )}
                    </div>
                </div>
            </div>

            {leaderboardEligible && showLeaderboard && (
                <>
                    {error ? (
                        <div role="alert">{error}</div>
                    ) : (
                        <FixtureLeaderboard
                            data={leaderboard}
                            loading={leaderboardLoading}
                        />
                    )}
                </>
            )}
        </div>
    );
}

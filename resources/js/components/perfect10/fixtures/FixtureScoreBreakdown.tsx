import type { FixturePrediction } from '@/types/perfect10/prediction';

type FixtureScoreBreakdownProps = {
    prediction: FixturePrediction | null | undefined;
};

function getPoints(
    correct: boolean | null,
    points: number,
): number | null {
    if (correct === null) {
        return null;
    }

    return correct ? points : 0;
}

function PointsCell({
    points,
}: {
    points: number | null;
}) {
    return (
        <div
            className={`fixtureBreakdownValue ${
                points !== null && points > 0 ? 'scored' : ''
            }`}
        >
            {points ?? '–'}
        </div>
    );
}

export default function FixtureScoreBreakdown({
    prediction,
}: FixtureScoreBreakdownProps) {
    // A saved prediction may not have been scored yet.
    if (!prediction || prediction.points_total === null) {
        return null;
    }

    const resultPoints = getPoints(
        prediction.correct_result,
        3,
    );

    const homePoints = getPoints(
        prediction.correct_home_goals,
        2,
    );

    const awayPoints = getPoints(
        prediction.correct_away_goals,
        2,
    );

    const goalDifferencePoints = getPoints(
        prediction.correct_goal_difference,
        2,
    );

    const totalGoalsPoints = getPoints(
        prediction.correct_total_goals,
        1,
    );

    const totalPoints = prediction.points_total;

    const isPerfect10 =
        prediction.perfect_10 === true ||
        totalPoints === 10;

    const predictionScore = [
        prediction.pred_home_goals,
        prediction.pred_away_goals,
    ].join('–');

    return (
        <div className="fixtureScoreBreakdown">
            <div className="fixtureBreakdownHeader fixtureBreakdownLayout">
                <div
                    className="fixtureBreakdownCell predColumn"
                    title="Your prediction"
                >
                    Pred
                </div>

                <div
                    className="fixtureBreakdownCell"
                    title="Correct result"
                >
                    R
                </div>

                <div
                    className="fixtureBreakdownCell"
                    title="Correct home goals"
                >
                    H
                </div>

                <div
                    className="fixtureBreakdownCell"
                    title="Correct away goals"
                >
                    A
                </div>

                <div
                    className="fixtureBreakdownCell"
                    title="Correct goal difference"
                >
                    GD
                </div>

                <div
                    className="fixtureBreakdownCell"
                    title="Correct total goals"
                >
                    G
                </div>

                <div
                    className="fixtureBreakdownCell pointsColumn"
                    title="Total points"
                >
                    Pts
                </div>
            </div>

            <div
                className={`fixtureBreakdownRow fixtureBreakdownLayout ${
                    isPerfect10 ? 'perfect10' : ''
                }`}
            >
                <div className="fixtureBreakdownCell fixtureBreakdownPrediction predColumn">
                    {predictionScore}
                </div>

                <PointsCell points={resultPoints} />
                <PointsCell points={homePoints} />
                <PointsCell points={awayPoints} />
                <PointsCell points={goalDifferencePoints} />
                <PointsCell points={totalGoalsPoints} />

                <div
                    className={`fixtureBreakdownCell fixtureBreakdownTotal pointsColumn ${
                        totalPoints > 0 ? 'scored' : ''
                    }`}
                >
                    {totalPoints}
                </div>
            </div>
        </div>
    );
}
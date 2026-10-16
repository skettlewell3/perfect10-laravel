import type {
    Fixture,
    FixtureStatus,
} from '@/types/perfect10/fixture';

import usePredictionWindowStatus from '@/hooks/perfect10/usePredictionWindowStatus';

type FixtureCardHeaderProps = {
    fixture: Fixture;
    predictionOpenAt?: string | null;
    predictionCloseAt?: string | null;
    showPredictionStatus?: boolean;
};

const FIXTURE_STATUSES: Record<
    FixtureStatus,
    { label: string; color: string }
> = {
    upcoming: { label: 'Upcoming', color: 'amber' },
    live_90: { label: 'Live', color: 'blue' },
    live_et: { label: 'Extra Time', color: 'blue' },
    finished: { label: 'Finished', color: 'grey' },
    postponed: { label: 'Postponed', color: 'grey' },
};

export function formatFixtureKickoff(kickoffAt: string): string {
    const date = new Date(kickoffAt);

    if (Number.isNaN(date.getTime())) return '';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).format(date);
}

export default function FixtureCardHeader({
    fixture,
    predictionOpenAt,
    predictionCloseAt,
    showPredictionStatus = true,
}: FixtureCardHeaderProps) {
    const openAt =
        predictionOpenAt === undefined
            ? fixture.prediction_open_at
            : predictionOpenAt;

    const closeAt =
        predictionCloseAt === undefined
            ? fixture.prediction_close_at
            : predictionCloseAt;

    const prediction = usePredictionWindowStatus(openAt, closeAt);

    const fixtureStatus = FIXTURE_STATUSES[fixture.fixture_status];

    const displayPrediction =
        showPredictionStatus &&
        fixture.fixture_status === 'upcoming' &&
        prediction.mode !== 'blank';

    return (
        <div className="fixtureCardHeader perFixtureCardHeader">
            <div className={`fixtureStatus ${fixtureStatus.color}`}>
                <span className="dot" />
                {fixtureStatus.label}
            </div>

            <div className="fcHeaderCenter">
                <div className="day">
                    {formatFixtureKickoff(fixture.kickoff_at)}
                </div>
            </div>

            {displayPrediction && (
                <div className={`perPredictionStatus ${prediction.color}`}>
                    {prediction.flip && prediction.countdown ? (
                        <span className="countdown">
                            {prediction.countdown}
                        </span>
                    ) : (
                        <>
                            <span className="label">
                                {prediction.label}
                            </span>
                            <span className="dot" />
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
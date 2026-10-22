import type { Fixture, FixtureStatus } from '@/types/perfect10/fixture';

import usePredictionWindowStatus from '@/hooks/perfect10/usePredictionWindowStatus';
import { formatFixtureKickoff } from './FixtureCardHeader';

type StageCardHeaderProps = {
    fixtures: Fixture[];
    predictionOpenAt?: string | null;
    predictionCloseAt?: string | null;
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

export default function StageCardHeader({
    fixtures,
    predictionOpenAt,
    predictionCloseAt,
}: StageCardHeaderProps) {
    const prediction = usePredictionWindowStatus(
        predictionOpenAt ?? null,
        predictionCloseAt ?? null,
    );

    if (fixtures.length === 0) return null;

    const fixture = fixtures[0];

    const fixtureStatus = FIXTURE_STATUSES[fixture.fixture_status];

    const showPrediction =
        fixture.fixture_status === 'upcoming' && prediction.mode !== 'blank';

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

            {showPrediction && (
                <div className={`perPredictionStatus ${prediction.color}`}>
                    {prediction.flip && prediction.countdown ? (
                        <span className="countdown">
                            {prediction.countdown}
                        </span>
                    ) : (
                        <>
                            <span className="label">{prediction.label}</span>
                            <span className="dot" />
                        </>
                    )}
                </div>
            )}
        </div>
    );
}

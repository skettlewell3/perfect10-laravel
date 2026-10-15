
import { useEffect, useState } from 'react';

import type {
    Fixture,
    FixtureStatus,
} from '@/types/perfect10/fixture';

type FixtureCardHeaderProps = {
    fixture: Fixture;
    predictionOpenAt?: string | null;
    predictionCloseAt?: string | null;
    showPredictionStatus?: boolean;
};

type PredictionStatus = {
    mode: 'blank' | 'preOpen' | 'open' | 'closingSoon' | 'closed';
    label: string;
    color: string;
    countdownTo: number | null;
};

const HOUR = 1000 * 60 * 60;

const FIXTURE_STATUSES: Partial<
    Record<FixtureStatus, { label: string; color: string }>
> = {
    upcoming: { label: 'Upcoming', color: 'amber' },
    live_90: { label: 'Live', color: 'blue' },
    live_et: { label: 'Extra Time', color: 'blue' },
    finished: { label: 'Finished', color: 'grey' },
    postponed: { label: 'Postponed', color: 'grey' },
};

function getPredictionStatus(
    openAt: string | null,
    closeAt: string | null,
    now: number,
): PredictionStatus {
    const blank: PredictionStatus = {
        mode: 'blank',
        label: '',
        color: '',
        countdownTo: null,
    };

    if (!openAt || !closeAt) return blank;

    const open = new Date(openAt).getTime();
    const close = new Date(closeAt).getTime();

    if (!Number.isFinite(open) || !Number.isFinite(close)) {
        return blank;
    }

    const timeToOpen = open - now;
    const timeToClose = close - now;

    if (timeToOpen > 24 * HOUR) {
        return blank;
    }

    if (now < open) {
        return {
            mode: 'preOpen',
            label: 'OPEN SOON',
            color: 'grey',
            countdownTo: open,
        };
    }

    if (now < close) {
        const closingSoon = timeToClose < 12 * HOUR;

        return {
            mode: closingSoon ? 'closingSoon' : 'open',
            label: closingSoon ? 'Closes soon' : 'OPEN',
            color: closingSoon ? 'amber' : 'green',
            countdownTo: close,
        };
    }

    return {
        mode: 'closed',
        label: 'CLOSED',
        color: 'grey',
        countdownTo: null,
    };
}

function formatCountdown(
    target: number | null,
    now: number,
): string | null {
    if (target === null) return null;

    const difference = target - now;

    if (difference <= 0) return null;

    const hours = Math.floor(difference / HOUR);
    const minutes = Math.floor((difference / 60000) % 60);

    return `${hours}h ${minutes}m`;
}

function formatKickoff(kickoffAt: string): string {
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
    const [now, setNow] = useState(() => Date.now());
    const [flip, setFlip] = useState(false);

    useEffect(() => {
        const interval = window.setInterval(() => {
            setNow(Date.now());
        }, 60000);

        return () => window.clearInterval(interval);
    }, []);

    useEffect(() => {
        const interval = window.setInterval(() => {
            setFlip((previous) => !previous);
            setNow(Date.now());
        }, 3000);

        return () => window.clearInterval(interval);
    }, []);

    const fixtureStatus = FIXTURE_STATUSES[fixture.fixture_status];

    const openAt =
        predictionOpenAt === undefined
            ? fixture.prediction_open_at
            : predictionOpenAt;

    const closeAt =
        predictionCloseAt === undefined
            ? fixture.prediction_close_at
            : predictionCloseAt;

    const prediction = getPredictionStatus(
        openAt,
        closeAt,
        now,
    );

    const countdown = formatCountdown(
        prediction.countdownTo,
        now,
    );

    const displayPrediction =
        showPredictionStatus &&
        fixture.fixture_status === 'upcoming' &&
        prediction.mode !== 'blank';

    return (
        <div className="fixtureCardHeader perFixtureCardHeader">
            {fixtureStatus && (
                <div
                    className={`fixtureStatus ${fixtureStatus.color}`}
                >
                    <span className="dot" />
                    {fixtureStatus.label}
                </div>
            )}

            <div className="fcHeaderCenter">
                <div className="day">
                    {formatKickoff(fixture.kickoff_at)}
                </div>
            </div>

            {displayPrediction && (
                <div
                    className={`perPredictionStatus ${prediction.color}`}
                >
                    {flip && countdown ? (
                        <span className="countdown">
                            {countdown}
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

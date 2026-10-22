import { useEffect, useState } from 'react';

import type { Gameweek } from '@/types/perfect10';

type Props = {
    activeGameweek: Gameweek;
    hasSubmitted: boolean;
    now: Date;
};

export default function GameweekSnapshot({
    activeGameweek,
    hasSubmitted,
    now,
}: Props) {
    const [showCountdownScreen, setShowCountdownScreen] = useState(false);

    const closeAt = activeGameweek.prediction_close_at
        ? new Date(activeGameweek.prediction_close_at)
        : null;

    const secondsRemaining = closeAt
        ? Math.max(
              0,
              Math.floor(
                  (closeAt.getTime() - now.getTime()) / 1000,
              ),
          )
        : 0;

    const showCountdown =
        secondsRemaining > 0 && secondsRemaining < 86400;

    useEffect(() => {
        if (!showCountdown) {
            setShowCountdownScreen(false);
            return;
        }

        const timer = window.setInterval(() => {
            setShowCountdownScreen((current) => !current);
        }, 5000);

        return () => window.clearInterval(timer);
    }, [showCountdown]);

    const hours = Math.floor(secondsRemaining / 3600);
    const minutes = Math.floor((secondsRemaining % 3600) / 60);
    const seconds = secondsRemaining % 60;

    const countdown = [hours, minutes, seconds]
        .map((value) => String(value).padStart(2, '0'))
        .join(':');

    const deadline = closeAt
        ? [
              closeAt.toLocaleTimeString('en-GB', {
                  hour: '2-digit',
                  minute: '2-digit',
              }),
              closeAt.toLocaleDateString('en-GB', {
                  day: '2-digit',
                  month: '2-digit',
              }),
          ].join(' ')
        : null;

    const displayCountdown =
        showCountdown && showCountdownScreen;

    return (
        <div className="gameweekSnapshot">
            <div className="gameweekSnapshotTitle">
                GW {activeGameweek.gameweek_number}
            </div>

            <div className="gameweekPredictionStatus">
                {hasSubmitted ? (
                    <>
                        <span>Submitted</span>

                        <span className="predictionStatusIcon success">
                            ✓
                        </span>
                    </>
                ) : (
                    <>
                        <span>Not submitted</span>

                        <span className="predictionStatusIcon failure">
                            ✗
                        </span>
                    </>
                )}
            </div>

            {closeAt && secondsRemaining > 0 && (
                <div className="gameweekDeadline">
                    {displayCountdown ? (
                        <>
                            <div className="gameweekDeadlineLabel">
                                Closes in:
                            </div>

                            <div className="gameweekDeadlineValue">
                                {countdown}
                            </div>
                        </>
                    ) : (
                        <>
                            <div className="gameweekDeadlineLabel">
                                Closes at:
                            </div>

                            <div className="gameweekDeadlineValue small">
                                {deadline}
                            </div>
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
import { useEffect, useState } from 'react';

import type { Fixture } from '@/types/perfect10/fixture';

type MatchScoreProps = {
    fixture: Fixture;
};

type ScoreDisplay = {
    home: number | null;
    away: number | null;
    label: string;
};

export default function MatchScore({ fixture }: MatchScoreProps) {
    const [showExtraTime, setShowExtraTime] = useState(false);

    const isFinished = fixture.fixture_status === 'finished';
    const isExtraTime = fixture.fixture_status === 'live_et';

    const hasExtraTime =
        isExtraTime ||
        fixture.resolution_type === 'extra_time' ||
        fixture.resolution_type === 'penalties';

    useEffect(() => {
        if (!hasExtraTime) return;

        const interval = window.setInterval(() => {
            setShowExtraTime((previous) => !previous);
        }, 4000);

        return () => window.clearInterval(interval);
    }, [hasExtraTime]);

    const ftScore: ScoreDisplay = {
        home: fixture.ft_home_goals,
        away: fixture.ft_away_goals,
        label: fixture.fixture_status === 'live_90' ? '' : 'FT:',
    };

    const finalScore: ScoreDisplay = {
        home: fixture.final_home_goals,
        away: fixture.final_away_goals,
        label: isExtraTime ? '' : 'AET:',
    };

    const score = hasExtraTime && showExtraTime ? finalScore : ftScore;

    const hasStarted =
        fixture.fixture_status === 'live_90' || isExtraTime || isFinished;

    const display: ScoreDisplay = hasStarted
        ? hasExtraTime
            ? score
            : {
                  home: isFinished
                      ? (fixture.final_home_goals ?? fixture.ft_home_goals)
                      : fixture.ft_home_goals,
                  away: isFinished
                      ? (fixture.final_away_goals ?? fixture.ft_away_goals)
                      : fixture.ft_away_goals,
                  label: isFinished ? 'FT:' : '',
              }
        : {
              home: null,
              away: null,
              label: '',
          };

    return (
        <div className="scoreRow">
            <div className="homeScore">{display.home ?? '–'}</div>

            <div className="vs">
                {display.label && (
                    <span className="label">{display.label}</span>
                )}
                V
            </div>

            <div className="awayScore">{display.away ?? '–'}</div>
        </div>
    );
}

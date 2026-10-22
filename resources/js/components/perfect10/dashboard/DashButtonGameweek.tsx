import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';

import type { Flavour, GameweekContext } from '@/types/perfect10';
import type { Fixture } from '@/types/perfect10/fixture';
import type { PredictionsMap } from '@/types/perfect10/prediction';
import type { GameweekReview } from './DashboardContainer';

import { GameweekLogo } from './DashboardLogos';
import GameweekSnapshot from './GameweekSnapshot';
import GameweekReviewSnapshot from './GameweekReviewSnapshot';

type Props = {
    gameweekContext: GameweekContext;
    flavour: Flavour;
    fixtures: Fixture[];
    predictionsMap: PredictionsMap;
    gameweekReview: GameweekReview | null;
};

export default function DashButtonGameweek({
    gameweekContext,
    flavour,
    fixtures,
    predictionsMap,
    gameweekReview,
}: Props) {
    const { activeGameweek } = gameweekContext;

    const [now, setNow] = useState(() => new Date());
    const [screenIndex, setScreenIndex] = useState(0);

    useEffect(() => {
        const timer = window.setInterval(() => {
            setNow(new Date());
        }, 1000);

        return () => window.clearInterval(timer);
    }, []);

    const activeGameweekFixtures = useMemo(() => {
        if (!activeGameweek) return [];

        return fixtures.filter(
            (fixture) => fixture.gameweek_id === activeGameweek.gameweek_id,
        );
    }, [fixtures, activeGameweek]);

    const hasAnyPredictions = activeGameweekFixtures.some((fixture) =>
        Boolean(predictionsMap[fixture.fixture_id]),
    );

    const hasSubmitted =
        activeGameweekFixtures.length === 10 &&
        activeGameweekFixtures.every((fixture) =>
            Boolean(predictionsMap[fixture.fixture_id]),
        );

    const openAt = activeGameweek?.prediction_open_at
        ? new Date(activeGameweek.prediction_open_at)
        : null;

    const closeAt = activeGameweek?.prediction_close_at
        ? new Date(activeGameweek.prediction_close_at)
        : null;

    const predictionWindowOpen =
        activeGameweek?.status === 'active' &&
        openAt !== null &&
        closeAt !== null &&
        now >= openAt &&
        now < closeAt;

    const predictionWindowClosed = closeAt !== null && now >= closeAt;

    const screens = [];

    if (activeGameweek && predictionWindowOpen) {
        screens.push(
            <GameweekSnapshot
                key="current"
                activeGameweek={activeGameweek}
                hasSubmitted={hasSubmitted}
                now={now}
            />,
        );

        if (gameweekReview) {
            screens.push(
                <GameweekReviewSnapshot key="review" review={gameweekReview} />,
            );
        }
    } else if (activeGameweek && predictionWindowClosed) {
        if (hasAnyPredictions && gameweekReview) {
            screens.push(
                <GameweekReviewSnapshot key="review" review={gameweekReview} />,
            );
        } else {
            screens.push(
                <GameweekSnapshot
                    key="current"
                    activeGameweek={activeGameweek}
                    hasSubmitted={hasSubmitted}
                    now={now}
                />,
            );
        }
    } else if (gameweekReview) {
        screens.push(
            <GameweekReviewSnapshot key="review" review={gameweekReview} />,
        );
    }

    const screenCount = screens.length;

    useEffect(() => {
        setScreenIndex(0);
    }, [screenCount, activeGameweek?.gameweek_id]);

    useEffect(() => {
        if (screenCount < 2) return;

        const timer = window.setInterval(() => {
            setScreenIndex((current) => (current + 1) % screenCount);
        }, 5000);

        return () => window.clearInterval(timer);
    }, [screenCount]);

    return (
        <button
            type="button"
            className="dashboardButton"
            onClick={() => {
                router.get(`/${flavour.flavour_code.toLowerCase()}/gameweek`);
            }}
        >
            <div className="dashContent">
                <div className="dashButtonLabel">
                    <span>Gameweek</span>
                </div>

                {screenCount > 0 ? (
                    screens[screenIndex % screenCount]
                ) : (
                    <GameweekLogo />
                )}
            </div>
        </button>
    );
}

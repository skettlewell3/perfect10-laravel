import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

import type { Flavour, GameweekContext } from '@/types/perfect10';

import { GameweekLogo } from './DashboardLogos';
import GameweekSnapshot from './GameweekSnapshot';

type Props = {
    gameweekContext: GameweekContext;
    flavour: Flavour;
};

export default function DashButtonGameweek({
    gameweekContext,
    flavour,
}: Props) {
    const { activeGameweek } = gameweekContext;

    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const timer = window.setInterval(() => {
            setNow(new Date());
        }, 1000);

        return () => window.clearInterval(timer);
    }, []);

    const openAt = activeGameweek?.prediction_open_at
        ? new Date(activeGameweek.prediction_open_at)
        : null;

    const closeAt = activeGameweek?.prediction_close_at
        ? new Date(activeGameweek.prediction_close_at)
        : null;

    const predictionWindowOpen =
        activeGameweek !== null &&
        openAt !== null &&
        closeAt !== null &&
        now >= openAt &&
        now < closeAt;

    return (
        <button
            type="button"
            className="dashboardButton"
            onClick={() => {
                router.get('/gameweek', {
                    flavour: flavour.flavour_code,
                });
            }}
        >
            <div className="dashContent">
                <div className="dashButtonLabel">
                    <span>Gameweek</span>
                </div>

                {predictionWindowOpen && activeGameweek ? (
                    <GameweekSnapshot
                        activeGameweek={activeGameweek}
                        now={now}
                    />
                ) : (
                    <GameweekLogo />
                )}
            </div>
        </button>
    );
}

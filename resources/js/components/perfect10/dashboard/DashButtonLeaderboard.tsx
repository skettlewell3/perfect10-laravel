import { router } from '@inertiajs/react';

import type { Flavour } from '@/types/perfect10';

import { LeaderboardLogo } from './DashboardLogos';

type Props = {
    flavour: Flavour;
};

export default function DashButtonLeaderboard({ flavour }: Props) {
    return (
        <button
            type="button"
            className="dashboardButton"
            onClick={() => {
                router.get('/leaderboards', {
                    flavour: flavour.flavour_code,
                });
            }}
        >
            <div className="dashContent">
                <div className="dashButtonLabel">
                    <span>Leaderboard</span>
                </div>

                <LeaderboardLogo />
            </div>
        </button>
    );
}

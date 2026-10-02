import { router } from '@inertiajs/react';

import type { Flavour } from '@/types/perfect10';

import { ClubsLogo } from './DashboardLogos';

type Props = {
    flavour: Flavour;
};

export default function DashButtonClubs({ flavour }: Props) {
    return (
        <button
            type="button"
            className="dashboardButton"
            onClick={() => {
                router.get('/clubs', {
                    flavour: flavour.flavour_code,
                });
            }}
        >
            <div className="dashContent">
                <div className="dashButtonLabel">
                    <span>Clubs</span>
                </div>

                <ClubsLogo />
            </div>
        </button>
    );
}

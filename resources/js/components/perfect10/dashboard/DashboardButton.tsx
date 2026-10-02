import { router } from '@inertiajs/react';

import type { Flavour } from '@/types/perfect10';

type Props = {
    label: string;
    to: string;
    flavour: Flavour;
};

export default function DashboardButton({ label, to, flavour }: Props) {
    return (
        <button
            type="button"
            className="dashboardButton"
            onClick={() => {
                router.get(to, {
                    flavour: flavour.flavour_code,
                });
            }}
        >
            <div className="dashContent">
                <div className="dashButtonLabel leaderboard">
                    <span>{label}</span>
                </div>
            </div>
        </button>
    );
}

import { usePage } from '@inertiajs/react';

import type { Perfect10PageProps } from '@/types/perfect10';

export default function ProfileIdentity() {
    const { perfect10Profile, perfect10Shell } =
        usePage<Perfect10PageProps>().props;

    const globalPoints = perfect10Shell?.profileTicker.global?.total_points;

    return (
        <div className="profileIdentity">
            <div className="profileName">
                {perfect10Profile?.display_name ?? ''}
            </div>

            {globalPoints != null && (
                <div className="profilePoints">⭐'s: {globalPoints}</div>
            )}
        </div>
    );
}

import { usePage } from '@inertiajs/react';

import type { Perfect10PageProps } from '@/types/perfect10';

export default function ProfileIdentity() {
    const { perfect10Profile } = usePage<Perfect10PageProps>().props;

    return (
        <div className="profileIdentity">
            <div className="profileName">
                {perfect10Profile?.display_name ?? ''}
            </div>

            <div className="profilePoints">
                ⭐'s: -
            </div>
        </div>
    );
}
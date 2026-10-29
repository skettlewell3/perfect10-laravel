import type { FixtureStatus } from '@/types/perfect10/fixture';

export function canShowFixtureLeaderboard(status: FixtureStatus): boolean {
    return (
        status === 'live_90' || status === 'live_et' || status === 'finished'
    );
}

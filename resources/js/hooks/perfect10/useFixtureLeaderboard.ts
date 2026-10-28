import { useEffect, useState } from 'react';

import type { FixtureLeaderboardData } from '@/types/perfect10/fixture-leaderboard';

type Props = {
    fixtureId: number;
    flavourCode: string;
    enabled: boolean;
};

export default function useFixtureLeaderboard({
    fixtureId,
    flavourCode,
    enabled,
}: Props) {
    const [data, setData] = useState<FixtureLeaderboardData>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!enabled) return;

        const controller = new AbortController();

        const loadLeaderboard = async () => {
            setLoading(true);
            setError(null);

            try {
                const url = `/${encodeURIComponent(
                    flavourCode.trim().toLowerCase(),
                )}/fixtures/${fixtureId}/leaderboard`;

                const response = await fetch(url, {
                    signal: controller.signal,
                    headers: {
                        Accept: 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to load fixture leaderboard.');
                }

                const result: {
                    fixture_id: number;
                    leaderboard: FixtureLeaderboardData;
                } = await response.json();

                if (!controller.signal.aborted) {
                    setData(result.leaderboard);
                }
            } catch {
                if (!controller.signal.aborted) {
                    setError('Unable to load leaderboard.');
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        };

        void loadLeaderboard();

        return () => {
            controller.abort();
        };
    }, [enabled, fixtureId, flavourCode]);

    return {
        data,
        loading,
        error,
    };
}

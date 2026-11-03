import { useEffect, useState } from 'react';

import type { FixtureLeaderboardData } from '@/types/perfect10/fixture-leaderboard';

type Props = {
    fixtureId: number;
    flavourCode: string;
    enabled: boolean;
};

type FixtureLeaderboardResponse = {
    fixture_id: number;
    leaderboard: FixtureLeaderboardData;
};

const REFRESH_DEBOUNCE_MS = 300;

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

        let timer: ReturnType<typeof setTimeout> | null = null;
        let controller: AbortController | null = null;

        const loadLeaderboard = async () => {
            controller?.abort();

            const requestController = new AbortController();
            controller = requestController;

            setLoading(true);
            setError(null);

            try {
                const url = `/${encodeURIComponent(
                    flavourCode.trim().toLowerCase(),
                )}/fixtures/${fixtureId}/leaderboard`;

                const response = await fetch(url, {
                    signal: requestController.signal,
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error(
                        `Failed to load fixture leaderboard: HTTP ${response.status}`,
                    );
                }

                const result =
                    (await response.json()) as FixtureLeaderboardResponse;

                if (
                    !requestController.signal.aborted &&
                    result.fixture_id === fixtureId
                ) {
                    setData(result.leaderboard);
                }
            } catch (fetchError) {
                if (!requestController.signal.aborted) {
                    console.error(
                        '[Perfect10 Realtime] Leaderboard refresh failed:',
                        fetchError,
                    );

                    setError('Unable to load leaderboard.');
                }
            } finally {
                if (!requestController.signal.aborted) {
                    setLoading(false);
                }

                if (controller === requestController) {
                    controller = null;
                }
            }
        };

        const onResultUpdated = (event: Event) => {
            const updatedFixtureId = (event as CustomEvent<number>).detail;

            if (updatedFixtureId !== fixtureId) {
                return;
            }

            if (timer !== null) {
                clearTimeout(timer);
            }

            timer = setTimeout(() => {
                timer = null;
                void loadLeaderboard();
            }, REFRESH_DEBOUNCE_MS);
        };

        window.addEventListener(
            'perfect10:fixture-result-updated',
            onResultUpdated,
        );

        void loadLeaderboard();

        return () => {
            window.removeEventListener(
                'perfect10:fixture-result-updated',
                onResultUpdated,
            );

            if (timer !== null) {
                clearTimeout(timer);
            }

            controller?.abort();
        };
    }, [enabled, fixtureId, flavourCode]);

    return {
        data,
        loading,
        error,
    };
}

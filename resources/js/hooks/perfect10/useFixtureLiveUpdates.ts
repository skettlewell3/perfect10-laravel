import { useEffect, useMemo, useState } from 'react';

import type { Fixture } from '@/types/perfect10/fixture';

type FixtureLiveFields = Pick<
    Fixture,
    | 'fixture_id'
    | 'fixture_status'
    | 'prediction_open_at'
    | 'prediction_close_at'
    | 'ft_home_goals'
    | 'ft_away_goals'
    | 'final_home_goals'
    | 'final_away_goals'
    | 'result_type'
    | 'winner_team_id'
    | 'resolution_type'
>;

type FixtureLiveResponse = {
    fixture: FixtureLiveFields;
};

type FixtureUpdates = Record<number, FixtureLiveFields>;

const DEBOUNCE_MS = 300;

export default function useFixtureLiveUpdates(
    fixtures: Fixture[],
    flavourCode: string,
): Fixture[] {
    const [updates, setUpdates] = useState<FixtureUpdates>({});

    const fixtureIds = useMemo(
        () => new Set(fixtures.map((fixture) => fixture.fixture_id)),
        [fixtures],
    );

    useEffect(() => {
        const timers = new Map<number, ReturnType<typeof setTimeout>>();
        const controllers = new Map<number, AbortController>();

        const fetchFixture = async (fixtureId: number) => {
            const controller = new AbortController();

            controllers.set(fixtureId, controller);

            try {
                const url = `/${encodeURIComponent(
                    flavourCode.toLowerCase(),
                )}/fixtures/${fixtureId}/data`;

                const response = await fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                    },
                    cache: 'no-store',
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error(
                        `Fixture ${fixtureId} refresh failed: HTTP ${response.status}`,
                    );
                }

                const data = (await response.json()) as FixtureLiveResponse;

                if (
                    controller.signal.aborted ||
                    data.fixture?.fixture_id !== fixtureId
                ) {
                    return;
                }

                setUpdates((previous) => ({
                    ...previous,
                    [fixtureId]: data.fixture,
                }));
            } catch (error) {
                if (!controller.signal.aborted) {
                    console.error(
                        '[Perfect10 Realtime] Fixture refresh failed:',
                        error,
                    );
                }
            } finally {
                if (controllers.get(fixtureId) === controller) {
                    controllers.delete(fixtureId);
                }
            }
        };

        const onFixtureChanged = (event: Event) => {
            const fixtureId = (event as CustomEvent<number>).detail;

            if (!Number.isSafeInteger(fixtureId)) {
                return;
            }

            if (!fixtureIds.has(fixtureId)) {
                return;
            }

            const existingTimer = timers.get(fixtureId);

            if (existingTimer !== undefined) {
                clearTimeout(existingTimer);
            }

            controllers.get(fixtureId)?.abort();

            timers.set(
                fixtureId,
                setTimeout(() => {
                    timers.delete(fixtureId);
                    void fetchFixture(fixtureId);
                }, DEBOUNCE_MS),
            );
        };

        window.addEventListener('perfect10:fixture-changed', onFixtureChanged);

        return () => {
            window.removeEventListener(
                'perfect10:fixture-changed',
                onFixtureChanged,
            );

            for (const timer of timers.values()) {
                clearTimeout(timer);
            }

            for (const controller of controllers.values()) {
                controller.abort();
            }
        };
    }, [fixtureIds, flavourCode]);

    return useMemo(
        () =>
            fixtures.map((fixture) => {
                const update = updates[fixture.fixture_id];

                if (!update) {
                    return fixture;
                }

                const predictionsOpen =
                    update.fixture_status === 'upcoming' &&
                    fixture.predictions_open &&
                    update.prediction_open_at !== null &&
                    update.prediction_close_at !== null &&
                    Date.now() >=
                        new Date(update.prediction_open_at).getTime() &&
                    Date.now() < new Date(update.prediction_close_at).getTime();

                return {
                    ...fixture,
                    ...update,
                    predictions_open: predictionsOpen,
                };
            }),
        [fixtures, updates],
    );
}

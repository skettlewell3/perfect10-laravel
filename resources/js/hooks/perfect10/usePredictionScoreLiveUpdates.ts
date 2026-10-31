
import { useEffect, useMemo, useState } from 'react';

import type {
    FixturePrediction,
    PredictionsMap,
} from '@/types/perfect10/prediction';

type PredictionResponse = {
    prediction: FixturePrediction | null;
};

const REFRESH_DEBOUNCE_MS = 300;

export default function usePredictionScoreLiveUpdates(
    predictionsMap: PredictionsMap,
    flavourCode: string,
): PredictionsMap {
    const [updates, setUpdates] = useState<PredictionsMap>({});

    const predictionIds = useMemo(
        () => new Set(Object.keys(predictionsMap).map(Number)),
        [predictionsMap],
    );

    useEffect(() => {
        const timers = new Map<number, ReturnType<typeof setTimeout>>();
        const controllers = new Map<number, AbortController>();

        const fetchPrediction = async (fixtureId: number) => {
            controllers.get(fixtureId)?.abort();

            const controller = new AbortController();
            controllers.set(fixtureId, controller);

            try {
                const url = `/${encodeURIComponent(
                    flavourCode.trim().toLowerCase(),
                )}/fixtures/${fixtureId}/prediction-data`;

                const response = await fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/json',
                    },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error(
                        `Prediction refresh failed: HTTP ${response.status}`,
                    );
                }

                const result =
                    (await response.json()) as PredictionResponse;

                if (
                    controller.signal.aborted ||
                    !result.prediction ||
                    result.prediction.fixture_id !== fixtureId
                ) {
                    return;
                }

                setUpdates((previous) => ({
                    ...previous,
                    [fixtureId]: result.prediction as FixturePrediction,
                }));
            } catch (error) {
                if (!controller.signal.aborted) {
                    console.error(
                        '[Perfect10 Realtime] Prediction refresh failed:',
                        error,
                    );
                }
            } finally {
                if (controllers.get(fixtureId) === controller) {
                    controllers.delete(fixtureId);
                }
            }
        };

        const onResultUpdated = (event: Event) => {
            const fixtureId = (event as CustomEvent<number>).detail;

            if (!Number.isSafeInteger(fixtureId)) {
                return;
            }

            if (!predictionIds.has(fixtureId)) {
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
                    void fetchPrediction(fixtureId);
                }, REFRESH_DEBOUNCE_MS),
            );
        };

        window.addEventListener(
            'perfect10:fixture-result-updated',
            onResultUpdated,
        );

        return () => {
            window.removeEventListener(
                'perfect10:fixture-result-updated',
                onResultUpdated,
            );

            for (const timer of timers.values()) {
                clearTimeout(timer);
            }

            for (const controller of controllers.values()) {
                controller.abort();
            }
        };
    }, [predictionIds, flavourCode]);

    return useMemo(
        () => ({
            ...predictionsMap,
            ...updates,
        }),
        [predictionsMap, updates],
    );
}

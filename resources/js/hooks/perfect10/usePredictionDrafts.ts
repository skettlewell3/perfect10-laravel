import { useCallback, useEffect, useMemo, useState } from 'react';

import type { Fixture } from '@/types/perfect10/fixture';
import type {
    PredictionDraft,
    PredictionsMap,
} from '@/types/perfect10/prediction';

export type PredictionPayload = {
    fixture_id: number;
    pred_home_goals: number;
    pred_away_goals: number;
};

type PredictionDrafts = Record<number, PredictionDraft>;

type UsePredictionDraftsOptions = {
    fixtures: Fixture[];
    predictionsMap: PredictionsMap;

    profileId: string | null;
    flavourId: number;
    scopeId: number | null;

    predictionCloseAt?: string | null;
};

type StoredDrafts = {
    drafts: PredictionDrafts;
    expiresAt: string | null;
};

const MIN_GOALS = 0;
const MAX_GOALS = 9;

function validGoal(value: string): boolean {
    if (!/^\d+$/.test(value)) return false;

    const goals = Number(value);

    return (
        Number.isInteger(goals) &&
        goals >= MIN_GOALS &&
        goals <= MAX_GOALS
    );
}

export function isValidPrediction(
    draft: PredictionDraft,
): boolean {
    return (
        validGoal(draft.home) &&
        validGoal(draft.away)
    );
}

export function isDirtyPrediction(
    draft: PredictionDraft,
    prediction: PredictionsMap[number] | undefined,
): boolean {
    if (!prediction) {
        return draft.home !== '' || draft.away !== '';
    }

    if (!isValidPrediction(draft)) {
        return (
            draft.home !== String(prediction.pred_home_goals) ||
            draft.away !== String(prediction.pred_away_goals)
        );
    }

    return (
        Number(draft.home) !== prediction.pred_home_goals ||
        Number(draft.away) !== prediction.pred_away_goals
    );
}

function loadDrafts(key: string): PredictionDrafts {
    try {
        const raw = sessionStorage.getItem(key);

        if (!raw) return {};

        const stored = JSON.parse(raw) as StoredDrafts;

        if (
            stored.expiresAt &&
            Date.now() >= new Date(stored.expiresAt).getTime()
        ) {
            sessionStorage.removeItem(key);
            return {};
        }

        return stored.drafts ?? {};
    } catch {
        return {};
    }
}

export default function usePredictionDrafts({
    fixtures,
    predictionsMap,
    profileId,
    flavourId,
    scopeId,
    predictionCloseAt = null,
}: UsePredictionDraftsOptions) {
    const storageKey =
        profileId && scopeId !== null
            ? `p10:prediction-drafts:${profileId}:${flavourId}:${scopeId}`
            : null;

    const [draftState, setDraftState] = useState<{
        key: string | null;
        drafts: PredictionDrafts;
    }>(() => ({
        key: storageKey,
        drafts: storageKey ? loadDrafts(storageKey) : {},
    }));

    // Do not expose drafts belonging to a previous profile or scope.
    const drafts =
        draftState.key === storageKey
            ? draftState.drafts
            : {};

    useEffect(() => {
        if (draftState.key === storageKey) return;

        setDraftState({
            key: storageKey,
            drafts: storageKey ? loadDrafts(storageKey) : {},
        });
    }, [draftState.key, storageKey]);

    useEffect(() => {
        if (!storageKey || draftState.key !== storageKey) return;

        if (Object.keys(drafts).length === 0) {
            sessionStorage.removeItem(storageKey);
            return;
        }

        if (
            predictionCloseAt &&
            Date.now() >= new Date(predictionCloseAt).getTime()
        ) {
            sessionStorage.removeItem(storageKey);
            return;
        }

        const stored: StoredDrafts = {
            drafts,
            expiresAt: predictionCloseAt,
        };

        sessionStorage.setItem(storageKey, JSON.stringify(stored));
    }, [
        storageKey,
        draftState.key,
        drafts,
        predictionCloseAt,
    ]);

    const updateDraft = useCallback((
        fixtureId: number,
        draft: PredictionDraft,
    ) => {
        if (!storageKey) return;
    
        setDraftState(previous => ({
            key: storageKey,
            drafts: {
                ...(previous.key === storageKey
                    ? previous.drafts
                    : loadDrafts(storageKey)),
                [fixtureId]: draft,
            },
        }));
    }, [storageKey]);

    const clearDrafts = useCallback(() => {
        if (!storageKey) return;

        sessionStorage.removeItem(storageKey);

        setDraftState({
            key: storageKey,
            drafts: {},
        });
    }, [storageKey]);

    const fixtureIds = useMemo(
        () => new Set(fixtures.map(f => f.fixture_id)),
        [fixtures],
    );

    const validChangedPredictions = useMemo(() => {
        return Object.entries(drafts)
            .filter(([id, draft]) => {
                const fixtureId = Number(id);

                if (!fixtureIds.has(fixtureId)) return false;

                return (
                    isValidPrediction(draft) &&
                    isDirtyPrediction(
                        draft,
                        predictionsMap[fixtureId],
                    )
                );
            })
            .map(([id, draft]): PredictionPayload => ({
                fixture_id: Number(id),
                pred_home_goals: Number(draft.home),
                pred_away_goals: Number(draft.away),
            }));
    }, [drafts, fixtureIds, predictionsMap]);

    const hasExistingSet =
        fixtures.length > 0 &&
        fixtures.every(
            fixture => Boolean(predictionsMap[fixture.fixture_id])
        );

    // Initial GWK submission must include every fixture.
    const hasCompleteDraftSet =
        fixtures.length > 0 &&
        validChangedPredictions.length === fixtures.length;

    // Once a complete set exists, individual changes are allowed.
    const isUpdating =
        hasExistingSet &&
        validChangedPredictions.length > 0;

    const canSubmitGameweek =
        hasCompleteDraftSet || isUpdating;

    return {
        drafts,
        updateDraft,
        clearDrafts,

        validChangedPredictions,

        hasExistingSet,
        hasCompleteDraftSet,
        isUpdating,
        canSubmitGameweek,
    };
}
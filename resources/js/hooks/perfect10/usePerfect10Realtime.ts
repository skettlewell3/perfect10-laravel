import { useEffect } from 'react';

import {
    PERFECT10_FIXTURE_TOPIC,
    supabaseRealtime,
} from '@/lib/perfect10/supabase-realtime';

type FixtureRealtimePayload = {
    id?: string;
    type?: string;
    fixture_id?: number;
};

export default function usePerfect10Realtime() {
    useEffect(() => {
        const channel = supabaseRealtime
            .channel(PERFECT10_FIXTURE_TOPIC, {
                config: {
                    private: false,
                },
            })
            .on('broadcast', { event: 'fixture_changed' }, ({ payload }) => {
                const update = payload as FixtureRealtimePayload;

                console.log(
                    '[Perfect10 Realtime] Fixture event received:',
                    update,
                );

                if (
                    !Number.isSafeInteger(update.fixture_id) ||
                    (update.fixture_id ?? 0) <= 0
                ) {
                    return;
                }

                const fixtureId = update.fixture_id as number;

                window.dispatchEvent(
                    new CustomEvent('perfect10:fixture-changed', {
                        detail: fixtureId,
                    }),
                );

                if (update.type === 'result_updated') {
                    window.dispatchEvent(
                        new CustomEvent('perfect10:fixture-result-updated', {
                            detail: fixtureId,
                        }),
                    );
                }
            })
            .subscribe((status, error) => {
                console.log('[Perfect10 Realtime] Status:', status);

                if (error) {
                    console.error(
                        '[Perfect10 Realtime] Subscription error:',
                        error,
                    );
                }
            });

        return () => {
            void supabaseRealtime.removeChannel(channel);
        };
    }, []);
}

import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

import MatchContentBanner from '@/components/perfect10/matches/MatchContentBanner';
import MatchFixtureCard from '@/components/perfect10/matches/MatchFixtureCard';
import LeagueTable from '@/components/perfect10/tables/LeagueTable';
import SubmitSlideUp from '@/components/perfect10/predictions/SubmitSlideUp';
import PredictionNotification from '@/components/perfect10/predictions/PredictionNotification';

import type { PredictionNotificationData } from '@/components/perfect10/predictions/PredictionNotification';
import type { FixtureTeam } from '@/components/perfect10/fixtures/FixtureCard';
import type { LeagueTableRowData } from '@/components/perfect10/tables/LeagueTable';
import type { Perfect10PageProps } from '@/types/perfect10';
import type { Fixture } from '@/types/perfect10/fixture';
import type { Gameweek } from '@/types/perfect10/gameweek';
import type { PredictionsMap } from '@/types/perfect10/prediction';

import usePredictionDrafts from '@/hooks/perfect10/usePredictionDrafts';

type MatchPageProps = Perfect10PageProps & {
    activeGameweek: Gameweek;
    fixture: Fixture;
    fixtures: Fixture[];
    predictionsMap: PredictionsMap;
    teamsMap: Record<number, FixtureTeam>;
    actualTable: LeagueTableRowData[];
    navigation: {
        previousFixtureId: number | null;
        nextFixtureId: number | null;
    };
};

export default function MatchPage() {
    const {
        perfect10Profile,
        flavour,
        activeGameweek,
        fixture,
        fixtures,
        predictionsMap,
        teamsMap,
        actualTable,
        navigation,
    } = usePage<MatchPageProps>().props;

    const [processing, setProcessing] = useState(false);

    const [notification, setNotification] =
        useState<PredictionNotificationData | null>(null);

    const predictionWindowOpen =
        activeGameweek.status === 'active' &&
        activeGameweek.prediction_open_at !== null &&
        activeGameweek.prediction_close_at !== null &&
        Date.now() >= new Date(activeGameweek.prediction_open_at).getTime() &&
        Date.now() < new Date(activeGameweek.prediction_close_at).getTime();

    const {
        drafts,
        updateDraft,
        clearDrafts,
        validChangedPredictions,
        isUpdating,
        canSubmitGameweek,
    } = usePredictionDrafts({
        fixtures,
        predictionsMap,
        profileId: perfect10Profile?.profile_id ?? null,
        flavourId: flavour.flavour_id,
        scopeId: activeGameweek.gameweek_id,
        predictionCloseAt: activeGameweek.prediction_close_at,
    });

    const canSubmit =
        perfect10Profile !== null &&
        predictionWindowOpen &&
        canSubmitGameweek &&
        !processing;

    const showSubmitSlideUp =
        perfect10Profile !== null &&
        predictionWindowOpen &&
        validChangedPredictions.length > 0;

    const matchUrl = (fixtureId: number) =>
        `/${flavour.flavour_code.trim().toLowerCase()}/matches/${fixtureId}`;

    const goToFixture = (fixtureId: number | null) => {
        if (fixtureId === null || processing) return;

        router.get(
            matchUrl(fixtureId),
            {},
            {
                preserveState: false,
            },
        );
    };

    const handleSubmit = () => {
        if (!canSubmit || processing) return;

        const wasUpdating = isUpdating;

        setNotification(null);
        setProcessing(true);

        router.post(
            `/${encodeURIComponent(flavour.flavour_code.trim().toLowerCase())}/predictions`,
            {
                predictions: validChangedPredictions,
            },
            {
                preserveScroll: true,

                onSuccess: () => {
                    clearDrafts();

                    setNotification({
                        type: 'success',
                        isUpdating: wasUpdating,
                    });
                },

                onError: (errors) => {
                    setNotification({
                        type: 'error',
                        isUpdating: wasUpdating,
                        message:
                            Object.values(errors)[0] ??
                            "Couldn't submit your predictions. Please try again.",
                    });
                },

                onFinish: () => {
                    setProcessing(false);
                },
            },
        );
    };

    const matchTitle = useMemo(
        () =>
            `${fixture.home_team_name ?? 'Home'} v ${fixture.away_team_name ?? 'Away'}`,
        [fixture.home_team_name, fixture.away_team_name],
    );

    return (
        <>
            <Head title={`Perfect10: ${matchTitle}`} />

            <div className="pageShell">
                <MatchContentBanner
                    currentFixture={fixture}
                    previousFixtureId={navigation.previousFixtureId}
                    nextFixtureId={navigation.nextFixtureId}
                    onNavigate={goToFixture}
                    disabled={processing}
                />

                <div className="scrollArea activeStageScroll">
                    <MatchFixtureCard
                        fixture={fixture}
                        prediction={predictionsMap[fixture.fixture_id] ?? null}
                        draft={drafts[fixture.fixture_id] ?? null}
                        teamsMap={teamsMap}
                        actualTable={actualTable}
                        predictionWindowOpen={predictionWindowOpen}
                        onDraftChange={
                            predictionWindowOpen
                                ? (draft) =>
                                      updateDraft(fixture.fixture_id, draft)
                                : undefined
                        }
                    />

                    <div className="statsSection">
                        <div className="statsSectionTitle">Form</div>

                        <LeagueTable data={actualTable} />
                    </div>
                </div>

                <SubmitSlideUp
                    visible={showSubmitSlideUp}
                    canSubmit={canSubmit}
                    isUpdating={isUpdating}
                    processing={processing}
                    onSubmit={handleSubmit}
                />

                <PredictionNotification
                    notification={notification}
                    onClose={() => setNotification(null)}
                />
            </div>
        </>
    );
}

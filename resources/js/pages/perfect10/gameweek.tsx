import { useEffect, useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

import GameweekContentBanner from '@/components/perfect10/gameweeks/GameweekContentBanner';
import type { GameweekNavigationItem } from '@/components/perfect10/gameweeks/GameweekNavigation';
import StageFixturesCard from '@/components/perfect10/fixtures/StageFixturesCard';
import SubmitSlideUp from '@/components/perfect10/predictions/SubmitSlideUp';
import PredictionNotification from '@/components/perfect10/predictions/PredictionNotification';

import type { PredictionNotificationData } from '@/components/perfect10/predictions/PredictionNotification';
import type { FixtureTeam } from '@/components/perfect10/fixtures/FixtureCard';
import type { Perfect10PageProps } from '@/types/perfect10';
import type { Fixture } from '@/types/perfect10/fixture';
import type { Gameweek } from '@/types/perfect10/gameweek';
import type { PredictionsMap } from '@/types/perfect10/prediction';

import usePredictionDrafts from '@/hooks/perfect10/usePredictionDrafts';
import useFixtureLiveUpdates from '@/hooks/perfect10/useFixtureLiveUpdates';
import usePredictionScoreLiveUpdates from '@/hooks/perfect10/usePredictionScoreLiveUpdates';

type FixtureGroup = [string, Fixture[]];

type GameweekPageProps = Perfect10PageProps & {
    activeGameweek: Gameweek | null;
    selectedGameweek: Gameweek | null;
    gameweeks: GameweekNavigationItem[];
    fixtures: Fixture[];
    predictionsMap: PredictionsMap;
    teamsMap: Record<number, FixtureTeam>;
};

type GameweekContentProps = {
    flavourCode: string;
    flavourId: number;
    gameweek: Gameweek;
    fixtureGroups: FixtureGroup[];
    fixtures: Fixture[];
    predictionsMap: PredictionsMap;
    livePredictionsMap: PredictionsMap;
    teamsMap: Record<number, FixtureTeam>;
    profileId: string | null;
};

function ReadOnlyGameweekContent({
    flavourCode,
    gameweek,
    fixtureGroups,
    livePredictionsMap,
    teamsMap,
}: Pick<
    GameweekContentProps,
    | 'flavourCode'
    | 'gameweek'
    | 'fixtureGroups'
    | 'livePredictionsMap'
    | 'teamsMap'
>) {
    return (
        <div className="scrollArea activeStageScroll">
            <div id="predictionForm">
                {fixtureGroups.map(([kickoff, group]) => (
                    <StageFixturesCard
                        key={kickoff}
                        flavourCode={flavourCode}
                        fixtures={group}
                        teamsMap={teamsMap}
                        predictionsMap={livePredictionsMap}
                        predictionOpenAt={gameweek.prediction_open_at}
                        predictionCloseAt={gameweek.prediction_close_at}
                        predictionWindowOpen={false}
                    />
                ))}
            </div>
        </div>
    );
}

function EditableGameweekContent({
    flavourCode,
    flavourId,
    gameweek,
    fixtureGroups,
    fixtures,
    predictionsMap,
    livePredictionsMap,
    teamsMap,
    profileId,
}: GameweekContentProps) {
    const [processing, setProcessing] = useState(false);

    const [notification, setNotification] =
        useState<PredictionNotificationData | null>(null);

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
        profileId,
        flavourId,
        scopeId: gameweek.gameweek_id,
        predictionCloseAt: gameweek.prediction_close_at,
    });

    const canSubmit = profileId !== null && canSubmitGameweek && !processing;

    const showSubmitSlideUp =
        profileId !== null && validChangedPredictions.length > 0;

    const handleSubmit = () => {
        if (!canSubmit || processing) return;

        const wasUpdating = isUpdating;

        setNotification(null);
        setProcessing(true);

        router.post(
            `/${encodeURIComponent(flavourCode.trim().toLowerCase())}/predictions`,
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

    return (
        <>
            <div className="scrollArea activeStageScroll">
                <div id="predictionForm">
                    {fixtureGroups.map(([kickoff, group]) => (
                        <StageFixturesCard
                            key={kickoff}
                            flavourCode={flavourCode}
                            fixtures={group}
                            teamsMap={teamsMap}
                            predictionsMap={livePredictionsMap}
                            predictionOpenAt={gameweek.prediction_open_at}
                            predictionCloseAt={gameweek.prediction_close_at}
                            predictionWindowOpen={true}
                            predictionDrafts={drafts}
                            onDraftChange={updateDraft}
                        />
                    ))}
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
        </>
    );
}

export default function GameweekPage() {
    const {
        perfect10Profile,
        flavour,
        activeGameweek,
        selectedGameweek,
        gameweeks,
        fixtures,
        predictionsMap,
        teamsMap,
    } = usePage<GameweekPageProps>().props;

    const liveFixtures = useFixtureLiveUpdates(fixtures, flavour.flavour_code);

    const livePredictionsMap = usePredictionScoreLiveUpdates(
        predictionsMap,
        flavour.flavour_code,
    );

    /*
     * Keep the window state reasonably current even if
     * the page remains open across a prediction deadline.
     */
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const interval = window.setInterval(() => {
            setNow(Date.now());
        }, 1000);

        return () => window.clearInterval(interval);
    }, []);

    const groupedFixtures = useMemo<FixtureGroup[]>(() => {
        const groups = new Map<string, Fixture[]>();

        const sortedFixtures = [...liveFixtures].sort(
            (a, b) =>
                new Date(a.kickoff_at).getTime() -
                new Date(b.kickoff_at).getTime(),
        );

        for (const fixture of sortedFixtures) {
            const kickoff = fixture.kickoff_at;
            const group = groups.get(kickoff) ?? [];

            group.push(fixture);
            groups.set(kickoff, group);
        }

        return Array.from(groups.entries());
    }, [liveFixtures]);

    const isActiveSelection =
        selectedGameweek !== null &&
        activeGameweek !== null &&
        selectedGameweek.gameweek_id === activeGameweek.gameweek_id;

    const predictionWindowOpen =
        isActiveSelection &&
        selectedGameweek.status === 'active' &&
        selectedGameweek.prediction_open_at !== null &&
        selectedGameweek.prediction_close_at !== null &&
        now >= new Date(selectedGameweek.prediction_open_at).getTime() &&
        now < new Date(selectedGameweek.prediction_close_at).getTime();

    const canEdit = predictionWindowOpen && perfect10Profile !== null;

    const contentProps = selectedGameweek
        ? {
              flavourCode: flavour.flavour_code,
              flavourId: flavour.flavour_id,
              gameweek: selectedGameweek,
              fixtureGroups: groupedFixtures,
              fixtures,
              predictionsMap,
              livePredictionsMap,
              teamsMap,
              profileId: perfect10Profile?.profile_id ?? null,
          }
        : null;

    return (
        <>
            <Head
                title={
                    selectedGameweek
                        ? `Gameweek ${selectedGameweek.gameweek_number}`
                        : 'Gameweek'
                }
            />

            <div className="pageShell">
                <GameweekContentBanner
                    flavourCode={flavour.flavour_code}
                    gameweeks={gameweeks}
                    selectedGameweekId={selectedGameweek?.gameweek_id ?? null}
                    activeGameweekId={activeGameweek?.gameweek_id ?? null}
                />

                {selectedGameweek === null || contentProps === null ? (
                    <div className="scrollArea activeStageScroll">
                        <p>No active gameweek available.</p>
                    </div>
                ) : groupedFixtures.length === 0 ? (
                    <div className="scrollArea activeStageScroll">
                        <p>No fixtures available for this gameweek.</p>
                    </div>
                ) : canEdit ? (
                    <EditableGameweekContent {...contentProps} />
                ) : (
                    <ReadOnlyGameweekContent {...contentProps} />
                )}
            </div>
        </>
    );
}

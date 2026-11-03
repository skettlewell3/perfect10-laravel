import { useMemo, useState } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

import MatchContentBanner from '@/components/perfect10/matches/MatchContentBanner';
import MatchFixtureCard from '@/components/perfect10/matches/MatchFixtureCard';
import FormContainer, {
    type MatchForm,
} from '@/components/perfect10/matches/FormContainer';
import FixtureLeaderboard from '@/components/perfect10/fixtures/FixtureLeaderboard';
import FixtureScoreBreakdown from '@/components/perfect10/fixtures/FixtureScoreBreakdown';
import LeagueTableSnapshot from '@/components/perfect10/tables/LeagueTableSnapshot';
import TeamComparison from '@/components/perfect10/teamComparison/TeamComparison';
import SubmitSlideUp from '@/components/perfect10/predictions/SubmitSlideUp';
import PredictionNotification from '@/components/perfect10/predictions/PredictionNotification';

import type { PredictionNotificationData } from '@/components/perfect10/predictions/PredictionNotification';
import type { FixtureTeam } from '@/components/perfect10/fixtures/FixtureCard';
import type { LeagueTableRowData } from '@/components/perfect10/tables/LeagueTable';
import type { Perfect10PageProps } from '@/types/perfect10';
import type { Fixture } from '@/types/perfect10/fixture';
import type { Gameweek } from '@/types/perfect10/gameweek';
import type { PredictionsMap } from '@/types/perfect10/prediction';
import type { TeamComparisonData } from '@/types/perfect10/team-comparison';

import useFixtureLiveUpdates from '@/hooks/perfect10/useFixtureLiveUpdates';
import useFixtureLeaderboard from '@/hooks/perfect10/useFixtureLeaderboard';
import usePredictionScoreLiveUpdates from '@/hooks/perfect10/usePredictionScoreLiveUpdates';
import usePredictionDrafts from '@/hooks/perfect10/usePredictionDrafts';

import { canShowFixtureLeaderboard } from '@/utils/perfect10/fixture-leaderboard';

type MatchPageProps = Perfect10PageProps & {
    activeGameweek: Gameweek | null;
    selectedGameweek: Gameweek | null;
    fixture: Fixture;
    fixtures: Fixture[];
    predictionsMap: PredictionsMap;
    teamsMap: Record<number, FixtureTeam>;
    actualTable: LeagueTableRowData[];
    form: MatchForm;
    formTeamsMap: Record<number, FixtureTeam>;
    comparison: TeamComparisonData;
    comparisonTeamsMap: Record<number, FixtureTeam>;
    navigation: {
        previousFixtureId: number | null;
        nextFixtureId: number | null;
    };
};

type MatchLeaderboardState = ReturnType<typeof useFixtureLeaderboard>;

type MatchStatsProps = {
    liveFixture: Fixture;
    livePrediction: PredictionsMap[number] | null;
    predictionWindowOpen: boolean;
    showForm: boolean;
    actualTable: LeagueTableRowData[];
    form: MatchForm;
    formTeamsMap: Record<number, FixtureTeam>;
    comparison: TeamComparisonData;
    comparisonTeamsMap: Record<number, FixtureTeam>;
    leaderboardEligible: boolean;
    leaderboard: MatchLeaderboardState;
};

type MatchContentProps = MatchStatsProps & {
    fixture: Fixture;
    fixtures: Fixture[];
    predictionsMap: PredictionsMap;
    teamsMap: Record<number, FixtureTeam>;
    flavourCode: string;
    flavourId: number;
};

type EditableMatchContentProps = MatchContentProps & {
    gameweek: Gameweek;
    profileId: string;
    onProcessingChange: (processing: boolean) => void;
};

type MatchFixtureDraft = ComponentProps<typeof MatchFixtureCard>['draft'];

function MatchStats({
    liveFixture,
    livePrediction,
    predictionWindowOpen,
    showForm,
    actualTable,
    form,
    formTeamsMap,
    comparison,
    comparisonTeamsMap,
    leaderboardEligible,
    leaderboard,
}: MatchStatsProps) {
    return (
        <>
            {!predictionWindowOpen && livePrediction !== null && (
                <div className="statsSection">
                    <FixtureScoreBreakdown prediction={livePrediction} />
                </div>
            )}

            {leaderboardEligible && (
                <div className="statsSection">
                    {leaderboard.error ? (
                        <div role="alert">{leaderboard.error}</div>
                    ) : (
                        <FixtureLeaderboard
                            data={leaderboard.data}
                            loading={leaderboard.loading}
                        />
                    )}
                </div>
            )}

            <div className="statsSection">
                <div className="statsSectionTitle">Form</div>

                <LeagueTableSnapshot data={actualTable} />

                {showForm && (
                    <FormContainer
                        form={form}
                        currentFixture={liveFixture}
                        teamsMap={formTeamsMap}
                    />
                )}
            </div>

            {liveFixture.home_team_id !== null &&
                liveFixture.away_team_id !== null && (
                    <div className="statsSection">
                        <div className="statsSectionTitle text-right">
                            Team Comparison
                        </div>

                        <TeamComparison
                            data={comparison}
                            teams={{
                                team1: liveFixture.home_team_id,
                                team2: liveFixture.away_team_id,
                            }}
                            teamsMap={comparisonTeamsMap}
                            team1ShortCode={liveFixture.home_short_code}
                            team2ShortCode={liveFixture.away_short_code}
                        />
                    </div>
                )}
        </>
    );
}

function MatchScrollContent({
    fixtureCard,
    statsProps,
}: {
    fixtureCard: ReactNode;
    statsProps: MatchStatsProps;
}) {
    return (
        <div className="scrollArea activeStageScroll">
            {fixtureCard}
            <MatchStats {...statsProps} />
        </div>
    );
}

function ReadOnlyMatchContent(props: MatchContentProps) {
    const { liveFixture, livePrediction, teamsMap, actualTable } = props;

    const fixtureCard = (
        <MatchFixtureCard
            fixture={liveFixture}
            prediction={livePrediction}
            draft={null}
            teamsMap={teamsMap}
            actualTable={actualTable}
            predictionWindowOpen={false}
        />
    );

    return <MatchScrollContent fixtureCard={fixtureCard} statsProps={props} />;
}

function EditableMatchContent({
    fixture,
    fixtures,
    predictionsMap,
    teamsMap,
    liveFixture,
    livePrediction,
    actualTable,
    flavourCode,
    flavourId,
    gameweek,
    profileId,
    onProcessingChange,
    ...statsProps
}: EditableMatchContentProps) {
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

    const canSubmit = canSubmitGameweek && !processing;

    const showSubmitSlideUp = validChangedPredictions.length > 0;

    const handleSubmit = () => {
        if (!canSubmit || processing) return;

        const wasUpdating = isUpdating;

        setNotification(null);
        setProcessing(true);
        onProcessingChange(true);

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
                    onProcessingChange(false);
                },
            },
        );
    };

    const fixtureCard = (
        <MatchFixtureCard
            fixture={liveFixture}
            prediction={livePrediction}
            draft={(drafts[fixture.fixture_id] ?? null) as MatchFixtureDraft}
            teamsMap={teamsMap}
            actualTable={actualTable}
            predictionWindowOpen={true}
            onDraftChange={(draft) => updateDraft(fixture.fixture_id, draft)}
        />
    );

    return (
        <>
            <MatchScrollContent
                fixtureCard={fixtureCard}
                statsProps={{
                    ...statsProps,
                    liveFixture,
                    livePrediction,
                    actualTable,
                    predictionWindowOpen: true,
                }}
            />

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

export default function MatchPage() {
    const {
        perfect10Profile,
        flavour,
        activeGameweek,
        selectedGameweek,
        fixture,
        fixtures,
        predictionsMap,
        teamsMap,
        actualTable,
        form,
        formTeamsMap,
        comparison,
        comparisonTeamsMap,
        navigation,
    } = usePage<MatchPageProps>().props;

    const [processing, setProcessing] = useState(false);

    const matchFixtures = useMemo(() => [fixture], [fixture]);

    const liveFixtures = useFixtureLiveUpdates(
        matchFixtures,
        flavour.flavour_code,
    );

    const liveFixture = liveFixtures[0] ?? fixture;

    const livePredictionsMap = usePredictionScoreLiveUpdates(
        predictionsMap,
        flavour.flavour_code,
    );

    const livePrediction = livePredictionsMap[fixture.fixture_id] ?? null;

    const isActiveSelection =
        activeGameweek !== null &&
        selectedGameweek !== null &&
        activeGameweek.gameweek_id === selectedGameweek.gameweek_id;

    const predictionWindowOpen =
        isActiveSelection &&
        activeGameweek.status === 'active' &&
        activeGameweek.prediction_open_at !== null &&
        activeGameweek.prediction_close_at !== null &&
        Date.now() >= new Date(activeGameweek.prediction_open_at).getTime() &&
        Date.now() < new Date(activeGameweek.prediction_close_at).getTime();

    const canEdit =
        predictionWindowOpen &&
        perfect10Profile !== null &&
        selectedGameweek !== null;

    const leaderboardEligible = canShowFixtureLeaderboard(
        liveFixture.fixture_status,
    );

    const leaderboard = useFixtureLeaderboard({
        fixtureId: fixture.fixture_id,
        flavourCode: flavour.flavour_code,
        enabled: leaderboardEligible,
    });

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

    const matchTitle = useMemo(
        () =>
            `${fixture.home_team_name ?? 'Home'} v ${fixture.away_team_name ?? 'Away'}`,
        [fixture.home_team_name, fixture.away_team_name],
    );

    const contentProps: MatchContentProps = {
        fixture,
        fixtures,
        predictionsMap,
        teamsMap,
        liveFixture,
        livePrediction,
        showForm: isActiveSelection,
        actualTable,
        flavourCode: flavour.flavour_code,
        flavourId: flavour.flavour_id,
        form,
        formTeamsMap,
        comparison,
        comparisonTeamsMap,
        leaderboardEligible,
        leaderboard,
        predictionWindowOpen: false,
    };

    return (
        <>
            <Head title={`Perfect10: ${matchTitle}`} />

            <div className="pageShell">
                <MatchContentBanner
                    currentFixture={liveFixture}
                    previousFixtureId={navigation.previousFixtureId}
                    nextFixtureId={navigation.nextFixtureId}
                    selectedGameweekNumber={
                        selectedGameweek?.gameweek_number ?? null
                    }
                    onNavigate={goToFixture}
                    disabled={processing}
                />

                {canEdit && selectedGameweek && perfect10Profile ? (
                    <EditableMatchContent
                        {...contentProps}
                        gameweek={selectedGameweek}
                        profileId={perfect10Profile.profile_id}
                        onProcessingChange={setProcessing}
                    />
                ) : (
                    <ReadOnlyMatchContent {...contentProps} />
                )}
            </div>
        </>
    );
}

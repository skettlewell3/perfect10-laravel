
import { useMemo } from 'react';
import { Head, usePage } from '@inertiajs/react';

import ContentBanner from '@/components/perfect10/app/ContentBanner';
import StageFixturesCard from '@/components/perfect10/fixtures/StageFixturesCard';
import type { FixtureTeam } from '@/components/perfect10/fixtures/FixtureCard';

import usePredictionDrafts from '@/hooks/perfect10/usePredictionDrafts';

import type { Perfect10PageProps } from '@/types/perfect10';
import type { Fixture } from '@/types/perfect10/fixture';
import type { Gameweek } from '@/types/perfect10/gameweek';
import type { PredictionsMap } from '@/types/perfect10/prediction';

type GameweekPageProps = Perfect10PageProps & {
    activeGameweek: Gameweek | null;
    fixtures: Fixture[];
    predictionsMap: PredictionsMap;
    teamsMap: Record<number, FixtureTeam>;
};

export default function GameweekPage() {
    const {
        perfect10Profile,
        flavour,
        activeGameweek,
        fixtures,
        predictionsMap,
        teamsMap,
    } = usePage<GameweekPageProps>().props;

    const groupedFixtures = useMemo(() => {
        const groups = new Map<string, Fixture[]>();

        const sortedFixtures = [...fixtures].sort(
            (a, b) =>
                new Date(a.kickoff_at).getTime() -
                new Date(b.kickoff_at).getTime()
        );

        for (const fixture of sortedFixtures) {
            const kickoff = fixture.kickoff_at;

            const group = groups.get(kickoff) ?? [];

            group.push(fixture);

            groups.set(kickoff, group);
        }

        return Array.from(groups.entries());
    }, [fixtures]);

    const now = Date.now();

    const predictionWindowOpen =
        activeGameweek?.status === 'active' &&
        activeGameweek.prediction_open_at !== null &&
        activeGameweek.prediction_close_at !== null &&
        now >= new Date(activeGameweek.prediction_open_at).getTime() &&
        now < new Date(activeGameweek.prediction_close_at).getTime();

    const {
        drafts,
        updateDraft,
        validChangedPredictions,
        hasExistingSet,
        canSubmitGameweek,
    } = usePredictionDrafts({
        fixtures,
        predictionsMap,
        profileId: perfect10Profile?.profile_id ?? null,
        flavourId: flavour.flavour_id,
        scopeId: activeGameweek?.gameweek_id ?? null,
        predictionCloseAt: activeGameweek?.prediction_close_at,
    });

    return (
        <>
            <Head title="Gameweek" />

            <div className="pageShell">
                <ContentBanner
                    title={
                        activeGameweek
                            ? `Gameweek ${activeGameweek.gameweek_number}`
                            : 'Gameweek'
                    }
                    fallbackUrl={`/?flavour=${encodeURIComponent(flavour.flavour_code)}`}
                />

                <div className="scrollArea activeStageScroll">
                    {!activeGameweek ? (
                        <p>No active gameweek available.</p>
                    ) : groupedFixtures.length === 0 ? (
                        <p>No fixtures available for this gameweek.</p>
                    ) : (
                        <div id="predictionForm">
                            {groupedFixtures.map(([kickoff, group]) => (
                                <StageFixturesCard
                                key={kickoff}
                                fixtures={group}
                                teamsMap={teamsMap}
                                predictionsMap={predictionsMap}
                                predictionOpenAt={activeGameweek.prediction_open_at}
                                predictionCloseAt={activeGameweek.prediction_close_at}
                                predictionWindowOpen={predictionWindowOpen}
                                predictionDrafts={drafts}
                                onDraftChange={updateDraft}
                            />
                            ))}
                        </div>
                    )}

                    <div>
                        <p>Existing complete set: {hasExistingSet ? 'Yes' : 'No'}</p>

                        <p>
                            Valid changed predictions: {validChangedPredictions.length}
                            {' / '}
                            {fixtures.length}
                        </p>

                        <p>
                            Can submit gameweek: {canSubmitGameweek ? 'Yes' : 'No'}
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

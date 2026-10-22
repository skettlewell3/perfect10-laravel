import { Head, usePage } from '@inertiajs/react';

import ContentBanner from '@/components/perfect10/app/ContentBanner';
import FixtureCard from '@/components/perfect10/fixtures/FixtureCard';
import type { Fixture } from '@/types/perfect10/fixture';
import type { FixtureTeam } from '@/components/perfect10/fixtures/FixtureCard';
import type { Perfect10PageProps } from '@/types/perfect10';
import type { PredictionsMap } from '@/types/perfect10/prediction';

type FixturesPageProps = Perfect10PageProps & {
    fixtures: Fixture[];
    teamsMap: Record<number, FixtureTeam>;
    predictionsMap: PredictionsMap;
};

export default function FixturesPage() {
    const { flavour, fixtures, teamsMap, predictionsMap } =
        usePage<FixturesPageProps>().props;

    const displayFixtures = [...fixtures]
        .sort((a, b) => {
            const aTime = new Date(a.kickoff_at).getTime();
            const bTime = new Date(b.kickoff_at).getTime();

            return bTime - aTime;
        })
        .filter(
            (fixture) =>
                fixture.fixture_status === 'upcoming' ||
                fixture.fixture_status === 'finished',
        );

    const upcomingFixtures = displayFixtures
        .filter((fixture) => fixture.fixture_status === 'upcoming')
        .reverse();

    const finishedFixtures = displayFixtures.filter(
        (fixture) => fixture.fixture_status === 'finished',
    );

    const previewFixtures = (
        upcomingFixtures.length > 0 ? upcomingFixtures : finishedFixtures
    ).slice(0, 10);

    return (
        <>
            <Head title="Fixtures" />

            <div className="pageShell">
                <ContentBanner title="Fixtures" />

                <div className="scrollArea fixturesList">
                    {previewFixtures.map((fixture) => (
                        <FixtureCard
                            key={fixture.fixture_id}
                            fixture={fixture}
                            formatCode={flavour.format_code}
                            teamsMap={teamsMap}
                            prediction={
                                predictionsMap[fixture.fixture_id] ?? null
                            }
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

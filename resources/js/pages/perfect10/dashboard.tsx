import { Head } from '@inertiajs/react';

import type { Perfect10PageProps } from '@/types/perfect10';
import type { PredictionsMap } from '@/types/perfect10/prediction';
import type { GameweekReview } from '@/components/perfect10/dashboard/DashboardContainer';

import DashboardContainer from '@/components/perfect10/dashboard/DashboardContainer';

type DashboardPageProps = Perfect10PageProps & {
    predictionsMap: PredictionsMap;
    gameweekReview: GameweekReview | null;
};

export default function Dashboard({
    flavour,
    gameweekContext,
    fixtures,
    predictionsMap,
    gameweekReview,
}: DashboardPageProps) {
    return (
        <>
            <Head title={`Perfect10: ${flavour.flavour_name}`} />

            <div className="pageShell">
                <div className="scrollArea dashboardScroll">
                    <DashboardContainer
                        fixtures={fixtures}
                        flavour={flavour}
                        gameweekContext={gameweekContext}
                        predictionsMap={predictionsMap}
                        gameweekReview={gameweekReview}
                    />
                </div>
            </div>
        </>
    );
}

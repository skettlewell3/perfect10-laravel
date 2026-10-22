import type { Flavour, GameweekContext } from '@/types/perfect10';
import type { Fixture } from '@/types/perfect10/fixture';
import type { PredictionsMap } from '@/types/perfect10/prediction';

import DashboardButton from './DashboardButton';
import DashButtonClubs from './DashButtonClubs';
import DashButtonFixture from './DashButtonFixture';
import DashButtonGameweek from './DashButtonGameweek';
import DashButtonLeaderboard from './DashButtonLeaderboard';

export type GameweekReview = {
    stage_code: string;
    rank: number | null;
    points_total: number;
    perfect_10s: number;
    correct_results: number;
    correct_home_goals: number;
    correct_away_goals: number;
    correct_goal_difference: number;
    correct_total_goals: number;
};

type Props = {
    fixtures: Fixture[];
    flavour: Flavour;
    gameweekContext: GameweekContext | null;
    predictionsMap: PredictionsMap;
    gameweekReview: GameweekReview | null;
};

export default function DashboardContainer({
    fixtures,
    flavour,
    gameweekContext,
    predictionsMap,
    gameweekReview,
}: Props) {
    const isGameweekFormat = flavour.format_code === 'GWK';

    return (
        <div id="dashboardContainer">
            <DashButtonFixture fixtures={fixtures} flavour={flavour} />

            <DashButtonLeaderboard flavour={flavour} />

            {isGameweekFormat && gameweekContext && (
                <DashButtonGameweek
                    flavour={flavour}
                    gameweekContext={gameweekContext}
                    fixtures={fixtures}
                    predictionsMap={predictionsMap}
                    gameweekReview={gameweekReview}
                />
            )}

            <DashButtonClubs flavour={flavour} />

            {isGameweekFormat && (
                <DashboardButton label="Stats" to="/stats" flavour={flavour} />
            )}
        </div>
    );
}

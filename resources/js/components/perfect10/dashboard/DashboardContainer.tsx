import type { Fixture, Flavour, GameweekContext } from '@/types/perfect10';

import DashboardButton from './DashboardButton';
import DashButtonClubs from './DashButtonClubs';
import DashButtonFixture from './DashButtonFixture';
import DashButtonGameweek from './DashButtonGameweek';
import DashButtonLeaderboard from './DashButtonLeaderboard';

type Props = {
    fixtures: Fixture[];
    flavour: Flavour;
    gameweekContext: GameweekContext | null;
};

export default function DashboardContainer({
    fixtures,
    flavour,
    gameweekContext,
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
                />
            )}

            <DashButtonClubs flavour={flavour} />

            {isGameweekFormat && (
                <DashboardButton label="Stats" to="/stats" flavour={flavour} />
            )}
        </div>
    );
}

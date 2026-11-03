import { router } from '@inertiajs/react';

import GameweekNavigation, {
    type GameweekNavigationItem,
} from './GameweekNavigation';

type GameweekContentBannerProps = {
    flavourCode: string;
    gameweeks: GameweekNavigationItem[];
    selectedGameweekId: number | null;
    activeGameweekId: number | null;
};

export default function GameweekContentBanner({
    flavourCode,
    gameweeks,
    selectedGameweekId,
    activeGameweekId,
}: GameweekContentBannerProps) {
    const handleBack = () => {
        router.visit(`/${flavourCode.trim().toLowerCase()}`, {
            replace: true,
        });
    };

    return (
        <div className="contentBanner gameweekContentBanner">
            <button
                type="button"
                className="backButton"
                onClick={handleBack}
                aria-label="Back to Dashboard"
            >
                <img src="/assets/svg/backArrow.svg" alt="" />
            </button>

            <div className="gameweekBannerNavigation">
                <GameweekNavigation
                    flavourCode={flavourCode}
                    gameweeks={gameweeks}
                    selectedGameweekId={selectedGameweekId}
                    activeGameweekId={activeGameweekId}
                />
            </div>
        </div>
    );
}

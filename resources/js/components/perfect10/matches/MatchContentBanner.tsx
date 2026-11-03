import { router, usePage } from '@inertiajs/react';

import MatchNav from './MatchNav';

import type { Perfect10PageProps } from '@/types/perfect10';
import type { Fixture } from '@/types/perfect10/fixture';

type MatchContentBannerProps = {
    currentFixture: Fixture;
    previousFixtureId: number | null;
    nextFixtureId: number | null;
    selectedGameweekNumber: number | null;
    onNavigate: (fixtureId: number | null) => void;
    disabled?: boolean;
};

export default function MatchContentBanner({
    currentFixture,
    previousFixtureId,
    nextFixtureId,
    selectedGameweekNumber,
    onNavigate,
    disabled = false,
}: MatchContentBannerProps) {
    const { flavour } = usePage<Perfect10PageProps>().props;

    const handleBack = () => {
        const flavourCode = flavour.flavour_code.trim().toLowerCase();

        const destination =
            selectedGameweekNumber !== null
                ? `/${flavourCode}/gameweek/${selectedGameweekNumber}`
                : `/${flavourCode}/fixtures`;

        router.visit(destination, {
            replace: true,
        });
    };

    return (
        <div className="contentBanner matchContentBanner">
            <button
                type="button"
                className="backButton"
                onClick={handleBack}
                aria-label={
                    selectedGameweekNumber !== null
                        ? 'Back to Gameweek'
                        : 'Back to Fixtures'
                }
            >
                <img src="/assets/svg/backArrow.svg" alt="" />
            </button>

            <div className="matchBannerNavigation">
                <MatchNav
                    currentFixture={currentFixture}
                    previousFixtureId={previousFixtureId}
                    nextFixtureId={nextFixtureId}
                    onNavigate={onNavigate}
                    disabled={disabled}
                />
            </div>
        </div>
    );
}

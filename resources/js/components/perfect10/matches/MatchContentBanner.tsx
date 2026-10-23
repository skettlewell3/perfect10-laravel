import { router, usePage } from '@inertiajs/react';

import MatchNav from './MatchNav';

import type { Perfect10PageProps } from '@/types/perfect10';
import type { Fixture } from '@/types/perfect10/fixture';

import './match-content-banner.css';

type MatchContentBannerProps = {
    currentFixture: Fixture;
    previousFixtureId: number | null;
    nextFixtureId: number | null;
    onNavigate: (fixtureId: number | null) => void;
    disabled?: boolean;
};

export default function MatchContentBanner({
    currentFixture,
    previousFixtureId,
    nextFixtureId,
    onNavigate,
    disabled = false,
}: MatchContentBannerProps) {
    const { flavour } = usePage<Perfect10PageProps>().props;

    const handleBack = () => {
        router.visit(`/${flavour.flavour_code.trim().toLowerCase()}/gameweek`, {
            replace: true,
        });
    };

    return (
        <div className="contentBanner matchContentBanner">
            <button
                type="button"
                className="backButton"
                onClick={handleBack}
                aria-label="Back to Gameweek"
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

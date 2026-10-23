import type { Fixture } from '@/types/perfect10/fixture';

type MatchNavProps = {
    currentFixture: Fixture;
    previousFixtureId: number | null;
    nextFixtureId: number | null;
    onNavigate: (fixtureId: number | null) => void;
    disabled?: boolean;
};

export default function MatchNav({
    currentFixture,
    previousFixtureId,
    nextFixtureId,
    onNavigate,
    disabled = false,
}: MatchNavProps) {
    return (
        <nav className="matchNav" aria-label="Match navigation">
            <button
                type="button"
                className="navArrow"
                onClick={() => onNavigate(previousFixtureId)}
                disabled={previousFixtureId === null || disabled}
                aria-label="Previous fixture"
            >
                <svg
                    viewBox="0 0 24 24"
                    className="arrow-icon"
                    aria-hidden="true"
                >
                    <path
                        d="M15 18l-6-6 6-6"
                        stroke="currentColor"
                        strokeWidth="2"
                        fill="none"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </svg>
            </button>

            <div className="matchNavFixture">
                <span>{currentFixture.home_team_name ?? 'Home'}</span>
                <span>v</span>
                <span>{currentFixture.away_team_name ?? 'Away'}</span>
            </div>

            <button
                type="button"
                className="navArrow"
                onClick={() => onNavigate(nextFixtureId)}
                disabled={nextFixtureId === null || disabled}
                aria-label="Next fixture"
            >
                <svg
                    viewBox="0 0 24 24"
                    className="arrow-icon"
                    aria-hidden="true"
                >
                    <path
                        d="M9 6l6 6-6 6"
                        stroke="currentColor"
                        strokeWidth="2"
                        fill="none"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </svg>
            </button>
        </nav>
    );
}

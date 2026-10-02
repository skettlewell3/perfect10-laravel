import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';

import type { Flavour } from '@/types/perfect10';
import type { Fixture } from '@/types/perfect10';
import { FixturesLogo } from './DashboardLogos';

import DashboardSnapshot from './DashboardSnapshot';
import FixtureSnapshot from './FixtureSnapshot';

type Props = {
    fixtures: Fixture[];
    flavour: Flavour;
};

export default function DashButtonFixture({ fixtures, flavour }: Props) {
    const [showLogo, setShowLogo] = useState(true);

    const snapshotFixtures = useMemo(() => {
        if (!fixtures.length) {
            return [];
        }

        const today = new Date().toDateString();

        const todaysFixtures = fixtures
            .filter((fixture) => {
                return new Date(fixture.kickoff_at).toDateString() === today;
            })
            .sort((a, b) => {
                return (
                    new Date(a.kickoff_at).getTime() -
                    new Date(b.kickoff_at).getTime()
                );
            });

        const liveFixtures = todaysFixtures.filter((fixture) => {
            return (
                fixture.fixture_status === 'live_90' ||
                fixture.fixture_status === 'live_et'
            );
        });

        return liveFixtures.length ? liveFixtures : todaysFixtures;
    }, [fixtures]);

    useEffect(() => {
        setShowLogo(true);

        if (!snapshotFixtures.length) {
            return;
        }

        const timer = window.setTimeout(() => {
            setShowLogo(false);
        }, 5000);

        return () => window.clearTimeout(timer);
    }, [snapshotFixtures]);

    return (
        <button
            type="button"
            className="dashboardButton"
            onClick={() => {
                router.get('/fixtures', {
                    flavour: flavour.flavour_code,
                });
            }}
        >
            <div className="dashContent">
                <div className="dashButtonLabel">
                    <span>Fixtures</span>
                </div>

                {showLogo ? (
                    <FixturesLogo />
                ) : (
                    <DashboardSnapshot
                        screens={snapshotFixtures.map((fixture) => (
                            <FixtureSnapshot
                                key={fixture.fixture_id}
                                fixture={fixture}
                            />
                        ))}
                        interval={5000}
                        loopFrom={0}
                    />
                )}
            </div>
        </button>
    );
}

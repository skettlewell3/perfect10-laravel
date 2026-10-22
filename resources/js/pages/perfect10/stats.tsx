import { useState } from 'react';
import { Head } from '@inertiajs/react';

import ContentBanner from '@/components/perfect10/app/ContentBanner';
import LeagueTableCard from '@/components/perfect10/tables/LeagueTableCard';

import type { Flavour } from '@/types/perfect10';
import type { LeagueTableRowData } from '@/components/perfect10/tables/LeagueTable';

type StatsView = 'football' | 'player';

type Props = {
    flavour: Flavour;
    actualTable: LeagueTableRowData[];
    predictedTable: LeagueTableRowData[];
};

export default function StatsPage({
    flavour,
    actualTable,
    predictedTable,
}: Props) {
    const [view, setView] = useState<StatsView>('football');

    const isGameweekFormat = flavour.format_code === 'GWK';

    return (
        <>
            <Head title={`Perfect10: ${flavour.flavour_name} Stats`} />

            <div className="pageShell">
                <ContentBanner title="Stats" />

                <div className="scrollArea dashboardScroll">
                    <div className="statsPage">
                        <div className="statsPageTabs">
                            <button
                                type="button"
                                className={view === 'football' ? 'active' : ''}
                                onClick={() => setView('football')}
                            >
                                {flavour.flavour_code.trim()} Stats
                            </button>

                            <button
                                type="button"
                                className={view === 'player' ? 'active' : ''}
                                onClick={() => setView('player')}
                            >
                                My Stats
                            </button>
                        </div>

                        {view === 'football' && (
                            <div className="statsPageContent">
                                {isGameweekFormat && (
                                    <LeagueTableCard
                                        actualTable={actualTable}
                                        predictedTable={predictedTable}
                                        defaultView="actual"
                                    />
                                )}
                            </div>
                        )}

                        {view === 'player' && (
                            <div className="statsPageContent">
                                <section className="appCard">
                                    <h2>My Stats</h2>

                                    <p>
                                        Your prediction statistics for{' '}
                                        {flavour.flavour_name} will appear here.
                                    </p>
                                </section>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

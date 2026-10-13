import { useState } from 'react';

import LeagueTable from './LeagueTable';
import LeagueTableComparison from './LeagueTableComparison';
import LeagueTableTabs, { type LeagueTableView } from './LeagueTableTabs';

import type { LeagueTableRowData } from './LeagueTable';

type Props = {
    actualTable: LeagueTableRowData[];
    predictedTable: LeagueTableRowData[];
    defaultView: LeagueTableView;
};

export default function LeagueTableCard({
    actualTable,
    predictedTable,
    defaultView,
}: Props) {
    const [view, setView] = useState<LeagueTableView>(defaultView);

    return (
        <section className="appCard leagueTableCard">
            <h2>League Tables</h2>

            <LeagueTableTabs view={view} setView={setView} />

            {view === 'actual' && <LeagueTable data={actualTable} />}

            {view === 'predicted' && <LeagueTable data={predictedTable} />}

            {view === 'comparison' && (
                <LeagueTableComparison
                    actual={actualTable}
                    predicted={predictedTable}
                />
            )}
        </section>
    );
}

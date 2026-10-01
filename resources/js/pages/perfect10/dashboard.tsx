import { Head } from '@inertiajs/react';
import type { Perfect10PageProps } from '@/types/perfect10';

export default function Dashboard({
    flavours,
    flavour,
    competition,
    campaign,
    stageContext,
    gameweekContext,
}: Perfect10PageProps) {
    return (
        <>
            <Head title={`Perfect10: ${flavour.flavour_name}`} />

            <div className="pageShell">
                <div className="scrollArea dashboardScroll">
                    <h1>Perfect10 Dashboard</h1>

                    <p>{flavour.flavour_name}</p>
                    <p>{competition.competition_name}</p>
                    <p>{campaign.label}</p>

                    <p>{flavours.length} active flavour(s)</p>

                    <p>Format: {flavour.format_code}</p>

                    <p>
                        Active stage:{' '}
                        {stageContext.activeStage?.stage_name ?? 'None'}
                    </p>

                    <p>
                        Ticker stage:{' '}
                        {stageContext.tickerStage?.stage_code ?? 'None'}
                    </p>

                    {gameweekContext && (
                        <>
                            <p>
                                Active gameweek:{' '}
                                {gameweekContext.activeGameweek
                                    ?.gameweek_number ?? 'None'}
                            </p>

                            <p>
                                Next gameweek:{' '}
                                {gameweekContext.nextGameweek
                                    ?.gameweek_number ?? 'None'}
                            </p>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

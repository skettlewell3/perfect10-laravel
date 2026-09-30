import { Head } from '@inertiajs/react';
import type { Perfect10PageProps } from '@/types/perfect10';

export default function Dashboard({
    flavours,
    flavour,
    competition,
    campaign,
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
                </div>
            </div>
        </>
    );
}

import { Head } from '@inertiajs/react';

type Flavour = {
    flavour_id: number;
    flavour_name: string;
    flavour_code: string;
    is_default: boolean;
};

type Competition = {
    competition_id: number;
    competition_name: string;
    competition_code: string;
};

type Campaign = {
    campaign_id: number;
    label: string;
    code: string;
};

type DashboardProps = {
    flavours: Flavour[];
    flavour: Flavour;
    competition: Competition;
    campaign: Campaign;
};

export default function Dashboard({
    flavours,
    flavour,
    competition,
    campaign,
}: DashboardProps) {
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

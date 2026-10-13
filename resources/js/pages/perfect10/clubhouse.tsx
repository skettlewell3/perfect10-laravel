import { Head, usePage } from '@inertiajs/react';

import ContentBanner from '@/components/perfect10/app/ContentBanner';
import ClubMembersList from '@/components/perfect10/clubs/ClubMembersList';
import LeaderboardHeader from '@/components/perfect10/leaderboard/LeaderboardHeader';
import LeaderboardRow, {
    type LeaderboardRowData,
} from '@/components/perfect10/leaderboard/LeaderboardRow';
import LeagueTableCard from '@/components/perfect10/tables/LeagueTableCard';
import type { LeagueTableRowData } from '@/components/perfect10/tables/LeagueTable';
import type { Perfect10PageProps } from '@/types/perfect10';

export interface ClubMemberData {
    member_profile_id: string;
    display_name: string;
    username: string | null;
    club_role: string;
}

interface ClubData {
    club_id: string;
    club_name: string;
    club_code: string;
    club_colour: string | null;
    club_role: string;
    is_active: boolean;
    invite_only: boolean;
}

interface ClubhousePageProps extends Perfect10PageProps {
    club: ClubData;
    members: ClubMemberData[];
    clubLeaderboard: LeaderboardRowData[];
    actualTable: LeagueTableRowData[];
    predictedTable: LeagueTableRowData[];
    permissions: {
        can_manage_club: boolean;
        can_manage_roles: boolean;
    };
}

export default function ClubhousePage() {
    const {
        club,
        members,
        clubLeaderboard,
        actualTable,
        predictedTable,
        permissions,
        flavour,
    } = usePage<ClubhousePageProps>().props;

    const hasLeaderboard = clubLeaderboard.length > 0;
    const isGameweekFormat = flavour.format_code === 'GWK';

    return (
        <>
            <Head title={club.club_name} />

            <div className="pageShell">
                <ContentBanner title={club.club_name} />

                <div className="scrollArea dashboardScroll">
                    {hasLeaderboard && (
                        <div className="clubLeaderboardContainer">
                            <LeaderboardHeader />

                            {clubLeaderboard.map((row) => (
                                <LeaderboardRow
                                    key={row.profile_id}
                                    row={row}
                                />
                            ))}
                        </div>
                    )}

                    <ClubMembersList
                        clubCode={club.club_code}
                        flavour={flavour}
                        clubMates={members}
                        canManageClub={permissions.can_manage_club}
                        canManageRoles={permissions.can_manage_roles}
                    />

                    {isGameweekFormat && (
                        <LeagueTableCard
                            actualTable={actualTable}
                            predictedTable={predictedTable}
                            defaultView="predicted"
                        />
                    )}
                </div>
            </div>
        </>
    );
}
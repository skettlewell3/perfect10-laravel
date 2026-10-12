import { Head, usePage } from '@inertiajs/react';

import ContentBanner from '@/components/perfect10/app/ContentBanner';
import UserClubList from '@/components/perfect10/clubs/UserClubList';
import type { Perfect10PageProps } from '@/types/perfect10';

export interface ClubMembershipData {
    club_id: string;
    club_name: string;
    club_code: string | null;
    club_colour: string | null;
    club_role: string;
    is_active: boolean;
    invite_only: boolean;
}

interface ClubsPageProps extends Perfect10PageProps {
    memberships: ClubMembershipData[];
}

export default function ClubsPage() {
    const { memberships, flavour } = usePage<ClubsPageProps>().props;

    return (
        <>
            <Head title="Clubs" />

            <div className="pageShell">
                <ContentBanner
                    title="Clubs"
                    fallbackUrl={`/${flavour.flavour_code.toLowerCase()}`}
                />

                <div className="scrollArea dashboardScroll">
                    <UserClubList 
                        memberships={memberships} 
                        flavour={flavour}
                    />
                </div>
            </div>
        </>
    );
}
import { Head, usePage } from '@inertiajs/react';

import ContentBanner from '@/components/perfect10/app/ContentBanner';
import ClubInvitesCard from '@/components/perfect10/clubs/ClubInvitesCard';
import ClubOwnershipTransferCard from '@/components/perfect10/clubs/ClubOwnershipTransferCard';
import UserClubList from '@/components/perfect10/clubs/UserClubList';
import type { Perfect10PageProps } from '@/types/perfect10';

export interface ClubMembershipData {
    club_id: string;
    club_name: string;
    club_code: string;
    club_colour: string | null;
    club_role: string;
    is_active: boolean;
    invite_only: boolean;
}

export interface ClubInviteData {
    invite_id: string;
    club_id: string;
    club_name: string;
    club_code: string;
    club_colour: string | null;
    sender_name: string;
    created_at: string;
}

export interface ClubOwnershipTransferRequestData {
    request_id: string;
    club_id: string;
    club_name: string;
    club_code: string;
    owner_profile_id: string;
    owner_display_name: string;
    created_at: string;
    expires_at: string;
}

interface ClubsPageProps extends Perfect10PageProps {
    memberships: ClubMembershipData[];
    invites: ClubInviteData[];
    ownershipTransferRequests: ClubOwnershipTransferRequestData[];
}

export default function ClubsPage() {
    const {
        memberships,
        invites,
        ownershipTransferRequests,
        flavour,
    } = usePage<ClubsPageProps>().props;

    return (
        <>
            <Head title="Clubs" />

            <div className="pageShell">
                <ContentBanner
                    title="Clubs"
                    fallbackUrl={`/${flavour.flavour_code.toLowerCase()}`}
                />

                <div className="scrollArea dashboardScroll">
                    <ClubInvitesCard
                        invites={invites}
                        flavour={flavour}
                    />

                    <UserClubList
                        memberships={memberships}
                        flavour={flavour}
                    />

                    <ClubOwnershipTransferCard
                        ownershipTransferRequests={ownershipTransferRequests}
                        flavour={flavour}
                    />
                </div>
            </div>
        </>
    );
}
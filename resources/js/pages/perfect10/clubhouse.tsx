import { Head, usePage } from '@inertiajs/react';

import ContentBanner from '@/components/perfect10/app/ContentBanner';
import ClubMembersList from '@/components/perfect10/clubs/ClubMembersList';
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
    club_code: string | null;
    club_colour: string | null;
    club_role: string;
    is_active: boolean;
    invite_only: boolean;
}

interface ClubhousePageProps extends Perfect10PageProps {
    club: ClubData;
    members: ClubMemberData[];
    permissions: {
        can_manage_club: boolean;
        can_manage_roles: boolean;
    };
}

export default function ClubhousePage() {
    const {
        club,
        members,
        permissions,
    } = usePage<ClubhousePageProps>().props;

    return (
        <>
            <Head title={club.club_name} />

            <div className="pageShell">
                <ContentBanner title={club.club_name} />

                <div className="scrollArea dashboardScroll">
                    <ClubMembersList
                        clubId={club.club_id}
                        clubMates={members}
                        canManageClub={permissions.can_manage_club}
                        canManageRoles={permissions.can_manage_roles}
                    />
                </div>
            </div>
        </>
    );
}
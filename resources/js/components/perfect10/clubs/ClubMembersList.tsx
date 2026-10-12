import { useState } from 'react';

import type { ClubMemberData } from '@/pages/perfect10/clubhouse';

type Props = {
    clubId: string;
    clubMates: ClubMemberData[];
    canManageClub: boolean;
    canManageRoles: boolean;
};

function formatClubRole(role: string) {
    return role.charAt(0).toUpperCase() + role.slice(1);
}

export default function ClubMembersList({
    clubId,
    clubMates,
    canManageClub,
    canManageRoles,
}: Props) {
    const [editing, setEditing] = useState(false);

    return (
        <section className="accountCard">
            <div className="cardHeader">
                <h2>Members</h2>

                {canManageClub && (
                    <button
                        type="button"
                        className="textButton"
                        onClick={() => setEditing(!editing)}
                    >
                        {editing ? 'Done' : 'Add/Edit Members'}
                    </button>
                )}
            </div>

            {clubMates.map((member) => (
                <div
                    key={member.member_profile_id}
                    className="clubMemberRow"
                >
                    <span className="displayName">
                        {member.display_name}
                    </span>

                    <span className="clubMemberRole">
                        {formatClubRole(member.club_role)}
                    </span>
                </div>
            ))}
        </section>
    );
}
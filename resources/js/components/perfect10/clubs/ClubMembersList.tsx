import { router } from '@inertiajs/react';
import { useState } from 'react';

import RemoveIcon from '@/components/perfect10/app/RemoveIcon';
import SendClubInviteForm from '@/components/perfect10/clubs/SendClubInviteForm';
import type { ClubMemberData } from '@/pages/perfect10/clubhouse';
import type { Flavour } from '@/types/perfect10';

type Props = {
    clubCode: string;
    flavour: Flavour;
    clubMates: ClubMemberData[];
    canManageClub: boolean;
    canManageRoles: boolean;
};

function formatClubRole(role: string) {
    return role.charAt(0).toUpperCase() + role.slice(1);
}

export default function ClubMembersList({
    clubCode,
    flavour,
    clubMates,
    canManageClub,
    canManageRoles,
}: Props) {
    const [editing, setEditing] = useState(false);

    const handleRoleChange = (memberProfileId: string, role: string) => {
        router.patch(
            `/${flavour.flavour_code.toLowerCase()}/clubs/${clubCode}/members/role`,
            {
                member_profile_id: memberProfileId,
                role,
            },
            {
                preserveScroll: true,
            },
        );
    };

    const handleRemoveMember = (
        memberProfileId: string,
        displayName: string,
    ) => {
        const confirmed = window.confirm(
            `Are you sure you want to remove ${displayName} from this club?`,
        );

        if (!confirmed) {
            return;
        }

        router.delete(
            `/${flavour.flavour_code.toLowerCase()}/clubs/${clubCode}/members`,
            {
                data: {
                    member_profile_id: memberProfileId,
                },
                preserveScroll: true,
            },
        );
    };

    const handleTransferOwnership = (
        memberProfileId: string,
        displayName: string,
    ) => {
        const confirmed = window.confirm(
            `Send an ownership transfer request to ${displayName}?`,
        );

        if (!confirmed) {
            return;
        }

        router.post(
            `/${flavour.flavour_code.toLowerCase()}/clubs/${clubCode}/ownership-transfer`,
            {
                target_profile_id: memberProfileId,
            },
            {
                preserveScroll: true,
            },
        );
    };

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

            {editing && canManageClub && (
                <SendClubInviteForm clubCode={clubCode} flavour={flavour} />
            )}

            {clubMates.map((member) => (
                <div key={member.member_profile_id} className="clubMemberRow">
                    <span className="displayName">{member.display_name}</span>

                    {editing &&
                    canManageRoles &&
                    member.club_role !== 'owner' ? (
                        <select
                            className="clubRoleSelect"
                            value={member.club_role}
                            onChange={(event) =>
                                handleRoleChange(
                                    member.member_profile_id,
                                    event.target.value,
                                )
                            }
                        >
                            <option value="member">Member</option>

                            <option value="captain">Captain</option>
                        </select>
                    ) : (
                        <span className="clubMemberRole">
                            {formatClubRole(member.club_role)}
                        </span>
                    )}

                    {editing &&
                        canManageRoles &&
                        member.club_role !== 'owner' && (
                            <button
                                type="button"
                                className="clubTransferButton"
                                onClick={() =>
                                    handleTransferOwnership(
                                        member.member_profile_id,
                                        member.display_name,
                                    )
                                }
                            >
                                Transfer
                            </button>
                        )}

                    {editing &&
                        canManageClub &&
                        member.club_role !== 'owner' && (
                            <button
                                type="button"
                                className="iconButton redBG"
                                onClick={() =>
                                    handleRemoveMember(
                                        member.member_profile_id,
                                        member.display_name,
                                    )
                                }
                                title="Remove member"
                            >
                                <RemoveIcon />
                            </button>
                        )}
                </div>
            ))}
        </section>
    );
}

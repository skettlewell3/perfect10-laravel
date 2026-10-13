import { router } from '@inertiajs/react';

import type { ClubInviteData } from '@/pages/perfect10/clubs';
import type { Flavour } from '@/types/perfect10';

type Props = {
    invites: ClubInviteData[];
    flavour: Flavour;
};

export default function ClubInvitesCard({
    invites,
    flavour,
}: Props) {
    const handleAccept = (inviteId: string) => {
        router.post(
            `/${flavour.flavour_code.toLowerCase()}/clubs/invites/accept`,
            {
                invite_id: inviteId,
            },
            {
                preserveScroll: true,
            },
        );
    };

    const handleDecline = (inviteId: string) => {
        router.post(
            `/${flavour.flavour_code.toLowerCase()}/clubs/invites/decline`,
            {
                invite_id: inviteId,
            },
            {
                preserveScroll: true,
            },
        );
    };

    if (!invites.length) {
        return null;
    }

    return (
        <section className="accountCard">
            <h2>
                Club Invites
            </h2>

            <div className="clubInviteList">
                {invites.map((invite) => (
                    <div
                        key={invite.invite_id}
                        className="clubInviteRow"
                    >
                        <div className="clubInviteMain">
                            <span className="clubName">
                                {invite.club_name}
                            </span>

                            <span className="clubRole">
                                Invited by {invite.sender_name}
                            </span>
                        </div>

                        <div className="clubInviteActions">
                            <button
                                type="button"
                                className="clubInviteAccept"
                                onClick={() =>
                                    handleAccept(invite.invite_id)
                                }
                            >
                                Accept
                            </button>

                            <button
                                type="button"
                                className="clubInviteDecline"
                                onClick={() =>
                                    handleDecline(invite.invite_id)
                                }
                            >
                                Decline
                            </button>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}
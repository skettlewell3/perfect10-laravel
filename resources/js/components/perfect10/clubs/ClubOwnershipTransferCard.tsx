import { router } from '@inertiajs/react';

import type { ClubOwnershipTransferRequestData } from '@/pages/perfect10/clubs';
import type { Flavour } from '@/types/perfect10';

type Props = {
    ownershipTransferRequests: ClubOwnershipTransferRequestData[];
    flavour: Flavour;
};

export default function ClubOwnershipTransferCard({
    ownershipTransferRequests,
    flavour,
}: Props) {
    const handleAccept = (requestId: string) => {
        const confirmed = window.confirm(
            'Are you sure you want to become the owner of this club?'
        );

        if (!confirmed) {
            return;
        }

        router.post(
            `/${flavour.flavour_code.toLowerCase()}/clubs/ownership-transfer/accept`,
            {
                request_id: requestId,
            },
            {
                preserveScroll: true,
            },
        );
    };

    const handleDecline = (requestId: string) => {
        router.post(
            `/${flavour.flavour_code.toLowerCase()}/clubs/ownership-transfer/decline`,
            {
                request_id: requestId,
            },
            {
                preserveScroll: true,
            },
        );
    };

    if (!ownershipTransferRequests.length) {
        return null;
    }

    return (
        <section className="accountCard">
            <h2>
                Ownership Requests
            </h2>

            <div className="ownershipTransferList">
                {ownershipTransferRequests.map((request) => (
                    <div
                        key={request.request_id}
                        className="ownershipTransferRow"
                    >
                        <div className="ownershipTransferMain">
                            <span className="clubName">
                                {request.club_name}
                            </span>

                            <span className="clubRole">
                                Ownership offered by {request.owner_display_name}
                            </span>
                        </div>

                        <div className="ownershipTransferActions">
                            <button
                                type="button"
                                className="ownershipTransferAccept"
                                onClick={() =>
                                    handleAccept(request.request_id)
                                }
                            >
                                Accept
                            </button>

                            <button
                                type="button"
                                className="ownershipTransferDecline"
                                onClick={() =>
                                    handleDecline(request.request_id)
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
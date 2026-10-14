import { Link } from '@inertiajs/react';

export type OwnedClubData = {
    profile_id: string;
    club_name: string;
    club_code: string;
    is_active: boolean;
};

type Props = {
    club: OwnedClubData;
};

export default function OwnedClubSummaryCard({ club }: Props) {
    const flavour = window.location.pathname.split('/').filter(Boolean)[0];

    return (
        <div className="myClub">
            <p>Club Name</p>

            <strong className="clubDetails">{club.club_name}</strong>

            {club.club_code && (
                <>
                    <p>Club Code</p>

                    <strong className="clubDetails">{club.club_code}</strong>
                </>
            )}

            <p>Status</p>

            <strong className="clubDetails">
                {club.is_active ? 'Active' : 'Inactive'}
            </strong>

            {!club.is_active && <p>Add members to activate your club.</p>}

            <Link
                className="manageClubLink"
                href={`/${flavour}/clubs/${club.club_code}`}
            >
                Manage Club
            </Link>
        </div>
    );
}

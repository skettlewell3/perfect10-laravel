import { Link } from '@inertiajs/react';

import type { ClubMembershipData } from '@/pages/perfect10/clubs';
import type { Flavour } from '@/types/perfect10';

type Props = {
    memberships: ClubMembershipData[];
    flavour: Flavour;
};

export default function UserClubList({ memberships, flavour, }: Props) {
    return (
        <section className="accountCard">
            <div className="cardHeader">
                <h2>My Clubs</h2>

                {memberships.length > 0 && (
                    <button
                        type="button"
                        className="textButton"
                    >
                        Manage Clubs
                    </button>
                )}
            </div>

            <div className="clubList">
                {memberships.map((club) => (
                    <div
                        key={club.club_id}
                        className="clubListRow"
                    >
                        <Link
                            href={`/${flavour.flavour_code.toLowerCase()}/clubs/${club.club_id}`}
                            className="clubListLink"
                        >
                            <div className="clubListMain">
                                <span className="clubName">
                                    {club.club_name}
                                </span>

                                <span className="clubRole">
                                    {club.club_role}
                                </span>
                            </div>

                            <div className="clubStats">
                                <span>Rank -</span>

                                <span>Points -</span>
                            </div>
                        </Link>
                    </div>
                ))}

                {memberships.length === 0 && (
                    <div className="clubListEmpty">
                        You aren't a member of any clubs yet.
                    </div>
                )}
            </div>
        </section>
    );
}
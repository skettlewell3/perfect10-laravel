import CreateClubForm from '@/components/perfect10/profile/CreateClubForm';
import OwnedClubSummaryCard from '@/components/perfect10/profile/OwnedClubSummaryCard';

type OwnedClubData = {
    profile_id: string;
    club_name: string;
    club_code: string;
    is_active: boolean;
};

type Props = {
    ownedClub: OwnedClubData | null;
};

export default function ProfileClubCard({ ownedClub }: Props) {
    return (
        <section className="accountCard club">
            <h2>My Club</h2>

            {ownedClub ? (
                <OwnedClubSummaryCard club={ownedClub} />
            ) : (
                <CreateClubForm />
            )}
        </section>
    );
}

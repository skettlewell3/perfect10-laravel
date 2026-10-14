import ContentBanner from '@/components/perfect10/app/ContentBanner';
import ProfileClubCard from '@/components/perfect10/profile/ProfileClubCard';
import ProfileDetailsCard from '@/components/perfect10/profile/ProfileDetailsCard';

type ProfileData = {
    profile_id: string;
    display_name: string;
    username: string;
    created_at: string;
};

type OwnedClubData = {
    profile_id: string;
    club_name: string;
    club_code: string;
    is_active: boolean;
};

type Props = {
    profile: ProfileData;
    ownedClub: OwnedClubData | null;
};

export default function Profile({ profile, ownedClub }: Props) {
    return (
        <div className="pageShell">
            <ContentBanner title="Profile" />

            <div className="scrollArea dashboardScroll">
                <ProfileDetailsCard profile={profile} />

                <ProfileClubCard ownedClub={ownedClub} />
            </div>
        </div>
    );
}

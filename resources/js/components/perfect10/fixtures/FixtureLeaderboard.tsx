import type { FixtureLeaderboardData } from '@/types/perfect10/fixture-leaderboard';

type Props = {
    data: FixtureLeaderboardData;
    loading?: boolean;
};

export default function FixtureLeaderboard({ data, loading = false }: Props) {
    return (
        <div className="fixtureLeaderboard">
            <div className="lbHeaderRow lbLayout">
                <div>#</div>
                <div className="displayName lb">Name</div>
                <div>Score</div>
                <div>Pts</div>
            </div>

            {loading && <div>Loading...</div>}

            {!loading &&
                data.map((row) => (
                    <div key={row.profile_id} className="lbRow lbLayout">
                        <div>{row.rank}</div>

                        <div className="displayName lb">{row.display_name}</div>

                        <div>
                            <span>{row.pred_home_goals}</span>
                            <span>-</span>
                            <span>{row.pred_away_goals}</span>
                        </div>

                        <div>{row.points_total}</div>
                    </div>
                ))}
        </div>
    );
}

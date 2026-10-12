interface LeaderboardRowData {
    rank: number;
    profile_id: string;
    display_name: string;
    profile_type: string;
    predicted: number;
    correct_results: number;
    correct_home_goals: number;
    correct_away_goals: number;
    correct_goal_difference: number;
    correct_total_goals: number;
    perfect_10s: number;
    points_total: number;
}

interface Props {
    row: LeaderboardRowData;
}

export default function LeaderboardRow({ row }: Props) {
    return (
        <div className={`leaderboardRow leaderboardLayout ${row.profile_type}`}>
            <div className="rank">{row.rank}</div>

            <div
                className={`displayName leaderboard ${
                    row.display_name.length > 15 ? 'longName' : ''
                }`}
            >
                {row.display_name}
            </div>

            <div>{row.predicted}</div>

            <div className="focus double">
                <div>{row.points_total}</div>
                <div>{row.perfect_10s}</div>
            </div>

            <div>{row.correct_results}</div>
            <div>{row.correct_home_goals}</div>
            <div>{row.correct_away_goals}</div>
            <div>{row.correct_goal_difference}</div>
            <div>{row.correct_total_goals}</div>
        </div>
    );
}

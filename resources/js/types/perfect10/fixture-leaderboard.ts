export type FixtureLeaderboardRow = {
    rank: number;
    profile_id: string;
    display_name: string;

    pred_home_goals: number;
    pred_away_goals: number;

    correct_result: boolean;
    correct_home_goals: boolean;
    correct_away_goals: boolean;
    correct_goal_difference: boolean;
    correct_total_goals: boolean;

    points_total: number;
};

export type FixtureLeaderboardData = FixtureLeaderboardRow[];

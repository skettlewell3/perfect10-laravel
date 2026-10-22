export type PredictionDraft = {
    home: string;
    away: string;
};

export type FixturePrediction = {
    fixture_id: number;
    prediction_id: number;
    profile_id: string;

    pred_home_goals: number;
    pred_away_goals: number;

    prediction_created_at: string;
    prediction_updated_at: string;

    correct_result: boolean | null;
    correct_home_goals: boolean | null;
    correct_away_goals: boolean | null;
    correct_goal_difference: boolean | null;
    correct_total_goals: boolean | null;

    points_total: number | null;
    perfect_10: boolean | null;
};

export type PredictionsMap = Record<number, FixturePrediction>;

export type TeamComparisonMode = 'generic' | 'direct';

export type TeamComparisonTeams = {
    team1: number;
    team2: number;
};

export type TeamComparisonRowData = {
    opponent_id: number;
    opponent_name: string;
    opponent_short: string | null;

    t1_home_gf: number | null;
    t1_home_ga: number | null;
    t1_home_pts: number | null;
    t1_home_swing: number | null;

    t1_away_gf: number | null;
    t1_away_ga: number | null;
    t1_away_pts: number | null;
    t1_away_swing: number | null;

    t1_total_points: number | null;
    t1_total_swing: number | null;

    t2_home_gf: number | null;
    t2_home_ga: number | null;
    t2_home_pts: number | null;
    t2_home_swing: number | null;

    t2_away_gf: number | null;
    t2_away_ga: number | null;
    t2_away_pts: number | null;
    t2_away_swing: number | null;

    t2_total_points: number | null;
    t2_total_swing: number | null;
};

export type TeamComparisonData = TeamComparisonRowData[];

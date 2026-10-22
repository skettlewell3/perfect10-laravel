export type FixtureStatus =
    | 'upcoming'
    | 'live_90'
    | 'live_et'
    | 'finished'
    | 'postponed';

export type StageType = 'group' | 'knockout' | 'gameweek' | 'rearranged';

export type GameweekStatus = 'active' | 'finished' | 'upcoming';

export type Fixture = {
    // Campaign
    fixture_id: number;
    campaign_id: number;
    campaign_label: string;

    // Competition
    competition_id: number;
    competition_name: string;
    competition_code: string;

    // Gameweek
    gameweek_id: number | null;
    gameweek_number: number | null;
    gameweek_status: GameweekStatus | null;

    // Stage
    stage_id: number;
    stage_name: string;
    stage_code: string;
    stage_type: StageType;
    stage_order: number;
    group_letter: string | null;

    // Kickoff
    kickoff_at: string;
    fixture_status: FixtureStatus;
    day: string;
    ko: string;

    // Venue
    venue_name: string;
    city: string;
    country: string;
    country_code: string;
    timezone: string;

    // Teams
    home_team_id: number | null;
    home_team_name: string | null;
    home_short_code: string | null;

    away_team_id: number | null;
    away_team_name: string | null;
    away_short_code: string | null;

    // Knockout
    tie_id: number | null;
    team_a_id: number | null;
    team_b_id: number | null;
    winner_team_id: number | null;
    resolution_type: string | null;

    // Results
    ft_home_goals: number | null;
    ft_away_goals: number | null;
    final_home_goals: number | null;
    final_away_goals: number | null;
    result_type: string | null;

    // Predictions
    prediction_open_at: string | null;
    prediction_close_at: string | null;
    predictions_open: boolean;
};

export type Perfect10PageProps = {
    flavours: Flavour[];
    flavour: Flavour;
    competition: Competition;
    campaign: Campaign;

    stageContext: StageContext;
    gameweekContext: GameweekContext | null;
    fixtures: Fixture[];
};

export type Flavour = {
    flavour_id: number;
    flavour_name: string;
    flavour_code: string;
    is_default: boolean;

    competition_id: number;
    competition_name: string;
    competition_code: string;

    format_id: number;
    format_name: string;
    format_code: string;

    active_campaign_id: number;
    active_campaign_code: string;
    active_campaign_label: string;
};

export type Competition = {
    competition_id: number;
    competition_name: string;
    competition_code: string;
};

export type Campaign = {
    campaign_id: number;
    label: string;
    code: string;
};

export type Stage = {
    stage_id: number;
    campaign_id: number;
    competition_id: number;
    stage_name: string;
    stage_code: string;
    stage_type: string;
    order_index: number;
    is_active: boolean;
    is_live: boolean;
    is_finished: boolean;
};

export type Gameweek = {
    gameweek_id: number;
    campaign_id: number;
    stage_id: number;
    gameweek_number: number;
    status: string;
    prediction_open_at: string | null;
    prediction_close_at: string | null;
};

export type StageContext = {
    stages: Stage[];
    activeStage: Stage | null;
    tickerStage: Stage | null;
};

export type GameweekContext = {
    gameweeks: Gameweek[];
    activeGameweek: Gameweek | null;
    nextGameweek: Gameweek | null;
};

export type Fixture = {
    fixture_id: number;
    kickoff_at: string;

    fixture_status:
        | 'upcoming'
        | 'live_90'
        | 'live_et'
        | 'finished'
        | 'postponed';

    home_team_name: string | null;
    away_team_name: string | null;

    venue_name: string;
    final_home_goals: number | null;
    final_away_goals: number | null;
};

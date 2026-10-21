export type Gameweek = {
    gameweek_id: number;
    gameweek_number: number;
    status: string;
    prediction_open_at: string | null;
    prediction_close_at: string | null;
};
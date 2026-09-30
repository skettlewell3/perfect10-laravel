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

export type Perfect10PageProps = {
    flavours: Flavour[];
    flavour: Flavour;
    competition: Competition;
    campaign: Campaign;
};

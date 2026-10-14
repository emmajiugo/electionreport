export type Option = {
    id: number;
    name: string;
};

export type ReasonOption = {
    value: string;
    label: string;
};

export type Party = {
    id: number;
    acronym: string;
    name: string;
    logo_url: string | null;
    candidate: string | null;
    running_mate: string | null;
};

export type PollingUnitDetails = {
    name: string;
    code: string;
    ward: string;
    lga: string;
    state: string;
};

export type ResultSheet = {
    id: number;
    url: string;
    uploaded_at: string;
    is_yours: boolean;
};

export type Election = {
    id: number;
    name: string;
};

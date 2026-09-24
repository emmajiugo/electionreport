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

export type Election = {
    id: number;
    name: string;
};

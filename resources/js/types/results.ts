export type AreaResult = {
    id: number;
    code: string;
    name: string;
    reports: number;
    held: number;
    not_held: number;
    voted: number;
    not_voted: number;
    leading_party: string | null;
};

export type PartyResult = {
    id: number;
    acronym: string;
    name: string;
    logo_url: string | null;
    votes: number;
};

export type ReasonResult = {
    value: string;
    label: string;
    count: number;
};

export type Results = {
    summary: {
        reports: number;
        held: number;
        not_held: number;
        voted: number;
        not_voted: number;
        polling_units_reporting: number;
        polling_units: number;
    };
    parties: PartyResult[];
    notHeldReasons: ReasonResult[];
    notVotedReasons: ReasonResult[];
    states: AreaResult[];
    lgas: AreaResult[];
    generatedAt: string;
};

export type MapMetric = 'reports' | 'not_held' | 'not_voted';

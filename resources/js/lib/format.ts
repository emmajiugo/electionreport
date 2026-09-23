const numberFormat = new Intl.NumberFormat('en-NG');

export function formatNumber(value: number): string {
    return numberFormat.format(value);
}

/**
 * Whole-number percentage, or null when there is nothing to divide by.
 */
export function percent(part: number, whole: number): number | null {
    return whole > 0 ? Math.round((part / whole) * 100) : null;
}

export function formatPercent(value: number | null): string {
    return value === null ? '–' : `${value}%`;
}

const eur = new Intl.NumberFormat('sk-SK', { style: 'currency', currency: 'EUR' });

/** "1 234,50 €" (Slovak grouping, comma decimals). Null → en dash. */
export function formatEur(value: number | null | undefined): string {
    if (value === null || value === undefined) return '–';
    return eur.format(value);
}

/** Share of the priced plan already bought, 0–100 (rounded). */
export function boughtPercent(summary: Pick<App.Data.SpendSummaryData, 'total' | 'bought_total'>): number {
    if (summary.total <= 0) return 0;
    return Math.round((summary.bought_total / summary.total) * 100);
}

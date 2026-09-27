const quantityFormatter = new Intl.NumberFormat('sk-SK', { maximumFractionDigits: 2 });
const quantityInputFormatter = new Intl.NumberFormat('sk-SK', { maximumFractionDigits: 2, useGrouping: false });

export function parseDecimal(raw: string): number | null {
    const normalized = raw.replace(/\s/g, '').replace(',', '.');
    if (normalized === '') return null;
    const value = Number(normalized);
    return Number.isFinite(value) ? value : null;
}

export function formatQuantity(value: number): string {
    return quantityFormatter.format(value);
}

export function formatQuantityInput(value: number | null): string {
    return value === null ? '' : quantityInputFormatter.format(value);
}

export function lineTotal(unitPrice: number | null, quantity: number | null): number | null {
    if (unitPrice === null || quantity === null || Number.isNaN(unitPrice) || Number.isNaN(quantity)) return null;
    return Math.round(unitPrice * quantity * 100) / 100;
}

export function sumLineTotals(unitPrice: number | null, quantities: (number | null)[]): number | null {
    if (unitPrice === null) return null;
    const sum = quantities.reduce<number>((total, quantity) => total + (lineTotal(unitPrice, quantity ?? 0) ?? 0), 0);
    return Math.round(sum * 100) / 100;
}

export function sumQuantity(quantities: (number | null)[]): number {
    const sum = quantities.reduce<number>((total, quantity) => total + (quantity ?? 0), 0);
    return Math.round(sum * 100) / 100;
}

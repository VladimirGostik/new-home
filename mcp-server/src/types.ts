import type { EXPENSE_TYPES, ITEM_PRIORITIES, ITEM_STATUSES, PAYMENT_STATUSES } from "./constants.js";

export type ItemStatus = (typeof ITEM_STATUSES)[number];
export type ItemPriority = (typeof ITEM_PRIORITIES)[number];

export type ItemPaymentStatus = (typeof PAYMENT_STATUSES)[number];
export type ExpenseType = (typeof EXPENSE_TYPES)[number];

export interface Room {
    id: string;
    name: string;
}

/** One room share of an item. room_id null = "Celý dom". quantity is decimal (e.g. 12.5 m²). */
export interface ItemAllocation {
    id: string;
    room_id: string | null;
    room_name: string | null;
    quantity: number;
    /** round(unit_price × quantity, 2); null when the item has no price. */
    line_total: number | null;
}

export interface Item {
    id: string;
    name: string;
    note: string | null;
    /** Legacy: the room when the item is in exactly one room, else null. */
    room_id: string | null;
    /** Legacy: single room name, or names joined ", " for multi-room items; null = Celý dom. */
    room_name: string | null;
    unit_price: number | null;
    /** Decimal total quantity = Σ allocation quantities. */
    quantity: number;
    /** Σ per-room line totals. */
    total_price: number | null;
    /** Per-room breakdown (API ≥ allocations release; optional for older backends). */
    allocations?: ItemAllocation[];
    url: string | null;
    assigned_user_id: string | null;
    assigned_user_name: string | null;
    status: ItemStatus;
    priority: ItemPriority;
    photo_url: string | null;
    photo_thumb_url: string | null;
    created_at: string;
    selected_variant_id: string | null;
    selected_variant_name: string | null;
    variants_count: number;
    /** Σ payments (real money, uncapped). Optional for older backends. */
    paid_total?: number;
    /** max(total_price − paid_total, 0); null when the item has no price. */
    remaining?: number | null;
    payment_status?: ItemPaymentStatus;
    /** max(paid_total − total_price, 0); 0 when unpriced. */
    overpaid?: number;
}

/** One ledger row: an item payment (item_id set) or a standalone expense. */
export interface Expense {
    id: string;
    item_id: string | null;
    item_name: string | null;
    title: string | null;
    display_name: string;
    type: ExpenseType;
    amount: number;
    /** Y-m-d */
    paid_at: string;
    note: string | null;
    created_by_name: string | null;
    created_at: string;
}

export interface ExpenseTotals {
    total: number;
    items_total: number;
    manual_total: number;
    count: number;
}

export interface ItemPaymentSummary {
    total_price: number | null;
    paid_total: number;
    remaining: number | null;
    payment_status: ItemPaymentStatus;
    overpaid: number;
}

/** POST/PATCH response of the expense and payment endpoints. */
export interface ExpenseResult {
    expense: Expense;
    item_summary: ItemPaymentSummary | null;
    warning: string | null;
}

export interface ItemPayments {
    payments: Expense[];
    summary: ItemPaymentSummary;
}

export interface ItemVariant {
    id: string;
    name: string;
    unit_price: number | null;
    url: string | null;
    photo_url: string | null;
    photo_thumb_url: string | null;
    vote_count: number;
    voter_names: string[];
    is_my_vote: boolean;
    is_selected: boolean;
}

export interface VariantComparison {
    text: string;
    generated_at: string;
    is_stale: boolean;
}

export interface ItemDetail {
    item: Item;
    variants: ItemVariant[];
    comparison: VariantComparison | null;
    payments?: Expense[];
}

export interface Paginated<T> {
    current_page: number;
    data: T[];
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface AuthToken {
    token: string;
    user: { id: string; name: string; email?: string } & Record<string, unknown>;
}

export type ExpensePage = Paginated<Expense> & { totals: ExpenseTotals };

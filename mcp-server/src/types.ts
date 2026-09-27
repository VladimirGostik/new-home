import type { ITEM_PRIORITIES, ITEM_STATUSES } from "./constants.js";

export type ItemStatus = (typeof ITEM_STATUSES)[number];
export type ItemPriority = (typeof ITEM_PRIORITIES)[number];

export interface Room {
    id: string;
    name: string;
}

export interface Item {
    id: string;
    name: string;
    note: string | null;
    room_id: string | null;
    room_name: string | null;
    unit_price: number | null;
    quantity: number;
    total_price: number | null;
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

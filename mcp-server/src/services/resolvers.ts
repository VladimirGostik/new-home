import { WHOLE_HOUSE_FILTER, WHOLE_HOUSE_LABEL } from "../constants.js";
import type { Item, ItemDetail, ItemVariant, Paginated, Room } from "../types.js";
import { ApiError, type NewHomeApiClient } from "./api-client.js";

const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export function isUuid(value: string): boolean {
    return UUID_RE.test(value.trim());
}

/** Lowercase + strip diacritics + collapse whitespace, so "Kúpeľňa" matches "kupelna". */
export function normalize(value: string): string {
    return value
        .normalize("NFD")
        .replace(/\p{Diacritic}/gu, "")
        .toLowerCase()
        .replace(/\s+/g, " ")
        .trim();
}

const WHOLE_HOUSE_ALIASES = new Set(["house", "cely dom", "whole house", "dom", "bez miestnosti", "none", "no room"]);

/** Resolved room reference: a room uuid, or "house" for items without a room. */
export type RoomRef = { kind: "room"; room: Room } | { kind: "house" };

export async function listRooms(client: NewHomeApiClient): Promise<Room[]> {
    return client.get<Room[]>("/rooms");
}

/** Resolve a room given by uuid, name (case/diacritics-insensitive, partial allowed if unique) or "house". */
export async function resolveRoom(client: NewHomeApiClient, input: string, knownRooms?: Room[]): Promise<RoomRef> {
    const needle = normalize(input);
    if (WHOLE_HOUSE_ALIASES.has(needle)) {
        return { kind: "house" };
    }

    const rooms = knownRooms ?? (await listRooms(client));
    const available = () => [...rooms.map((r) => `"${r.name}"`), `"${WHOLE_HOUSE_FILTER}" (${WHOLE_HOUSE_LABEL})`].join(", ");

    if (isUuid(input)) {
        const byId = rooms.find((r) => r.id.toLowerCase() === input.trim().toLowerCase());
        if (!byId) {
            throw new ApiError(`No room with id ${input}. Available rooms: ${available()}.`);
        }
        return { kind: "room", room: byId };
    }

    const exact = rooms.filter((r) => normalize(r.name) === needle);
    if (exact.length === 1 && exact[0]) {
        return { kind: "room", room: exact[0] };
    }
    const partial = rooms.filter((r) => normalize(r.name).includes(needle));
    if (partial.length === 1 && partial[0]) {
        return { kind: "room", room: partial[0] };
    }
    if (partial.length > 1) {
        throw new ApiError(`Room "${input}" is ambiguous — matches ${partial.map((r) => `"${r.name}"`).join(", ")}. Use the exact name or id.`);
    }
    throw new ApiError(`No room matches "${input}". Available rooms: ${available()}.`);
}

/**
 * Resolve an item given by uuid or name. A name is searched via the API; an exact
 * (normalized) name match wins, otherwise the single search hit. Ambiguity is an error.
 */
export async function resolveItemId(client: NewHomeApiClient, input: string): Promise<string> {
    const trimmed = input.trim();
    if (isUuid(trimmed)) {
        return trimmed;
    }

    const needle = normalize(trimmed);
    const page = await client.get<Paginated<Item>>("/items", { "filter[search]": trimmed, per_page: 20 });
    let hits = page.data;
    let total = page.total;
    if (hits.length === 0) {
        // The API search may be diacritics-sensitive ("sedacka" vs "Sedačka") — fall back to local matching.
        hits = (await fetchAllItems(client, {})).filter((i) => normalize(i.name).includes(needle));
        total = hits.length;
    }
    const exact = hits.filter((i) => normalize(i.name) === needle);

    const pick = exact.length === 1 ? exact[0] : hits.length === 1 ? hits[0] : undefined;
    if (pick) {
        return pick.id;
    }
    if (hits.length === 0) {
        throw new ApiError(`No item matches "${trimmed}". Use new_home_list_items (optionally with search) to find the item and pass its id.`);
    }
    const candidates = hits
        .slice(0, 10)
        .map((i) => `- ${i.name} (${i.room_name ?? WHOLE_HOUSE_LABEL}) — id ${i.id}`)
        .join("\n");
    throw new ApiError(`"${trimmed}" matches ${total} items. Ask which one is meant, or pass its id:\n${candidates}`);
}

/** Fetch every item matching the given API query (100 per page, capped at 20 pages). */
export async function fetchAllItems(client: NewHomeApiClient, query: Record<string, string | undefined>): Promise<Item[]> {
    const items: Item[] = [];
    for (let page = 1; page <= 20; page++) {
        const result = await client.get<Paginated<Item>>("/items", { ...query, per_page: 100, page });
        items.push(...result.data);
        if (result.current_page >= result.last_page) {
            break;
        }
    }
    return items;
}

/**
 * The API search is diacritics-sensitive ("sedacka" does not find "Sedačka"). When it finds nothing,
 * re-run the query without search and match names locally (case/diacritics-insensitive), paginating locally.
 */
export async function searchItemsLocally(
    client: NewHomeApiClient,
    query: Record<string, string | undefined>,
    search: string,
    page: number,
    perPage: number,
): Promise<Paginated<Item>> {
    const needle = normalize(search);
    const matches = (await fetchAllItems(client, query)).filter((i) => normalize(i.name).includes(needle));
    const lastPage = Math.max(1, Math.ceil(matches.length / perPage));
    const start = (page - 1) * perPage;
    const data = matches.slice(start, start + perPage);
    return {
        current_page: page,
        data,
        last_page: lastPage,
        per_page: perPage,
        total: matches.length,
        from: data.length > 0 ? start + 1 : null,
        to: data.length > 0 ? start + data.length : null,
    };
}

/** API allocation input row: room_id null = Celý dom. */
export interface AllocationInput {
    room_id: string | null;
    quantity: number;
}

/** Resolve [{room, quantity}] (names/ids/"house") into API allocation rows; rejects the same room twice. */
export async function resolveAllocations(
    client: NewHomeApiClient,
    rows: ReadonlyArray<{ room: string; quantity: number }>,
): Promise<AllocationInput[]> {
    const needsRooms = rows.some((row) => !WHOLE_HOUSE_ALIASES.has(normalize(row.room)));
    const rooms = needsRooms ? await listRooms(client) : [];
    const seen = new Map<string, string>();
    const result: AllocationInput[] = [];
    for (const row of rows) {
        const ref = await resolveRoom(client, row.room, rooms);
        const key = ref.kind === "house" ? WHOLE_HOUSE_FILTER : ref.room.id;
        const label = ref.kind === "house" ? WHOLE_HOUSE_LABEL : ref.room.name;
        if (seen.has(key)) {
            throw new ApiError(
                `Room "${label}" is listed more than once ("${seen.get(key)}" and "${row.room}"). Merge them into one row with the summed quantity.`,
            );
        }
        seen.set(key, row.room);
        result.push({ room_id: ref.kind === "house" ? null : ref.room.id, quantity: row.quantity });
    }
    return result;
}

/** Find a variant of an item by UUID or name (case/diacritics-insensitive, unique partial match ok). */
export function resolveVariant(detail: ItemDetail, input: string): ItemVariant {
    const trimmed = input.trim();
    const list = () => detail.variants.map((v) => `- ${v.name} — id ${v.id}`).join("\n") || "(item has no variants)";

    if (isUuid(trimmed)) {
        const byId = detail.variants.find((v) => v.id.toLowerCase() === trimmed.toLowerCase());
        if (byId) return byId;
        throw new ApiError(`Item "${detail.item.name}" has no variant with id ${trimmed}. Its variants:\n${list()}`);
    }

    const needle = normalize(trimmed);
    const exact = detail.variants.filter((v) => normalize(v.name) === needle);
    const partial = detail.variants.filter((v) => normalize(v.name).includes(needle));
    const pick = exact.length === 1 ? exact[0] : partial.length === 1 ? partial[0] : undefined;
    if (pick) return pick;
    throw new ApiError(
        partial.length > 1
            ? `Variant "${trimmed}" is ambiguous for item "${detail.item.name}". Pass the variant id:\n${list()}`
            : `Item "${detail.item.name}" has no variant matching "${trimmed}". Its variants:\n${list()}`,
    );
}

/** Load item detail by UUID or name. */
export async function loadItemDetail(client: NewHomeApiClient, input: string): Promise<ItemDetail> {
    const id = await resolveItemId(client, input);
    return client.get<ItemDetail>(`/items/${encodeURIComponent(id)}`);
}

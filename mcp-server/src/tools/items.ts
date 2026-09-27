import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import { ITEM_PRIORITIES, ITEM_SORTS, ITEM_STATUSES, WHOLE_HOUSE_FILTER, WHOLE_HOUSE_LABEL } from "../constants.js";
import type { NewHomeApiClient } from "../services/api-client.js";
import { allocationsOf, formatPrice, itemDetailMarkdown, itemLine, itemResultMarkdown, roomsSummary, toolError, toolResult } from "../services/format.js";
import { type AllocationInput, loadItemDetail, resolveAllocations, resolveItemId, resolveRoom, searchItemsLocally } from "../services/resolvers.js";
import type { Item, ItemDetail, Paginated } from "../types.js";
import {
    itemIdSchema,
    itemRefSchema,
    photoUrlSchema,
    priceSchema,
    quantitySchema,
    responseFormatSchema,
    roomAllocationsSchema,
    roomSchema,
    urlSchema,
} from "./schemas.js";
import { ApiError } from "../services/api-client.js";

export function registerItemTools(server: McpServer, client: NewHomeApiClient): void {
    server.registerTool(
        "new_home_list_items",
        {
            title: "List shopping-list items",
            description: `List/search items on the family shopping list ("Náš nový dom"), paginated.

Filters (all optional, combinable): room (id, name or "${WHOLE_HOUSE_FILTER}" — matches items that have a share in that room; an item can be split across several rooms), search (substring of the item name, case/diacritics-insensitive), status (planned = "Plánované", bought = "Kúpené").
Default order is the app's shopping order; use sort to override.

Returns: { total, page, per_page, last_page, has_more, next_page, items: [{ id, name, note, allocations: [{ room_id, room_name, quantity, line_total }], unit_price, quantity, total_price, url, assigned_user_name, status, priority, selected_variant_name, variants_count, ... }] }.
Prices are EUR numbers. quantity is a DECIMAL total (e.g. 20.5 m²) = Σ allocations; total_price = Σ per-room line totals (round(unit_price × room quantity, 2)). room_name/room_id are legacy (joined names / null for multi-room items) — prefer allocations.
When filtered by room, the markdown shows that room's share of multi-room items and the page sum counts only that room's shares. If has_more is true, call again with page = next_page.

Use this to find an item's id before new_home_get_item / new_home_add_item_variant / new_home_save_variant_comparison when the name is ambiguous.`,
            inputSchema: z
                .object({
                    room: roomSchema.optional(),
                    search: z.string().trim().max(255).optional().describe("Case-insensitive search in item names, e.g. 'gauč'."),
                    status: z.enum(ITEM_STATUSES).optional().describe("planned = still to buy, bought = already bought."),
                    sort: z.enum(ITEM_SORTS).optional().describe("Sort field; prefix '-' for descending. Omit for the app's default shopping order."),
                    page: z.number().int().min(1).default(1).describe("Page number (1-based)."),
                    per_page: z.number().int().min(1).max(100).default(25).describe("Items per page (1–100, default 25)."),
                    response_format: responseFormatSchema,
                })
                .strict(),
            annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
        },
        async ({ room, search, status, sort, page, per_page, response_format }) => {
            try {
                let roomFilter: string | undefined;
                let roomLabel: string | undefined;
                let focusRoomId: string | null | undefined;
                if (room) {
                    const ref = await resolveRoom(client, room);
                    roomFilter = ref.kind === "house" ? WHOLE_HOUSE_FILTER : ref.room.id;
                    roomLabel = ref.kind === "house" ? WHOLE_HOUSE_LABEL : ref.room.name;
                    focusRoomId = ref.kind === "house" ? null : ref.room.id;
                }

                const baseQuery = { "filter[room]": roomFilter, "filter[status]": status, sort };
                let result = await client.get<Paginated<Item>>("/items", { ...baseQuery, "filter[search]": search, page, per_page });
                if (search && result.total === 0) {
                    result = await searchItemsLocally(client, baseQuery, search, page, per_page);
                }

                const hasMore = result.current_page < result.last_page;
                const structured = {
                    total: result.total,
                    page: result.current_page,
                    per_page: result.per_page,
                    last_page: result.last_page,
                    has_more: hasMore,
                    next_page: hasMore ? result.current_page + 1 : null,
                    filters: { room: roomLabel ?? null, search: search ?? null, status: status ?? null },
                    items: result.data,
                };

                return toolResult(response_format, structured, () => {
                    const filterDesc = [roomLabel && `miestnosť: ${roomLabel}`, search && `hľadanie: "${search}"`, status && `stav: ${status}`]
                        .filter(Boolean)
                        .join(", ");
                    const lines = [`# Položky${filterDesc ? ` (${filterDesc})` : ""}`, ""];
                    if (result.data.length === 0) {
                        lines.push("Žiadne položky nevyhovujú filtru.");
                    } else {
                        lines.push(`Zobrazené ${result.data.length} z ${result.total} (strana ${result.current_page}/${result.last_page}).`, "");
                        lines.push(...result.data.map((i) => itemLine(i, focusRoomId)));
                        const shareOf = (i: Item): number => {
                            if (focusRoomId === undefined) return i.total_price ?? 0;
                            const allocations = allocationsOf(i);
                            const share = allocations.find((a) => a.room_id === focusRoomId);
                            return share?.line_total ?? (allocations.length === 1 ? (i.total_price ?? 0) : 0);
                        };
                        const pageSum = result.data.reduce((sum, i) => sum + shareOf(i), 0);
                        lines.push("", `Súčet cien na tejto strane${roomLabel ? ` (podiel miestnosti ${roomLabel})` : ""}: ${formatPrice(pageSum)}`);
                        if (hasMore) lines.push(`Ďalšia strana: page=${result.current_page + 1}`);
                    }
                    return lines.join("\n");
                });
            } catch (error) {
                return toolError(error);
            }
        },
    );

    server.registerTool(
        "new_home_get_item",
        {
            title: "Get item detail",
            description: `Get full detail of one shopping-list item: item fields, per-room breakdown (allocations: room, decimal quantity, line total), all its variants (price, URL, vote count, who voted, whether it is the selected one) and the saved variant comparison (markdown text, generated_at, is_stale).

is_stale = true means variants changed after the comparison was saved — suggest regenerating it with new_home_save_variant_comparison.

Returns: { item: {..., allocations: [{ room_id, room_name, quantity, line_total }]}, variants: [{ id, name, unit_price, url, vote_count, voter_names, is_my_vote, is_selected }], comparison: { text, generated_at, is_stale } | null }`,
            inputSchema: z.object({ item: itemRefSchema, response_format: responseFormatSchema }).strict(),
            annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
        },
        async ({ item, response_format }) => {
            try {
                const id = await resolveItemId(client, item);
                const detail = await client.get<ItemDetail>(`/items/${encodeURIComponent(id)}`);
                return toolResult(response_format, { ...detail }, () => itemDetailMarkdown(detail));
            } catch (error) {
                return toolError(error);
            }
        },
    );

    server.registerTool(
        "new_home_create_item",
        {
            title: "Create shopping-list item",
            description: `Add a new item to the family shopping list. Every call creates a NEW item — check with new_home_list_items (search) first if it might already exist, to avoid duplicates.

Only name is required. Rooms — use ONE of the two forms:
- simple: room (name, id or "${WHOLE_HOUSE_FILTER}" = "${WHOLE_HOUSE_LABEL}") + quantity (default 1);
- split across rooms: rooms = [{ room, quantity }, ...], e.g. tiles "Kúpeľňa hore" 12.5 + "Kúpeľňa dole" 8 (m²). One unit price for the whole item; each room gets its own share.
Quantities are decimals (up to 2 places, e.g. 12.5). Prices are EUR per unit (piece / m² …), e.g. 24.9; totals are computed by the app per room (round(unit × qty, 2)) and summed.
Optional photo_url: a DIRECT image URL (jpg/png/webp, e.g. the shop's og:image) — the app downloads it; a failed download returns a validation error on photo_url.

Returns the created item: { id, name, allocations, unit_price, quantity, total_price, status, priority, ... }`,
            inputSchema: z
                .object({
                    name: z.string().trim().min(1).max(255).describe("Item name, e.g. 'Rohová sedačka' or 'Dlažba 60×60'."),
                    room: roomSchema.optional().describe("Simple form: one room (name/id/'house'). Do not combine with rooms."),
                    quantity: quantitySchema.optional().describe("Simple form: quantity for that one room (decimal, default 1). Do not combine with rooms."),
                    rooms: roomAllocationsSchema.optional(),
                    note: z.string().trim().max(5000).optional().describe("Free-text note (e.g. the unit: 'm²')."),
                    unit_price: priceSchema.optional(),
                    url: urlSchema.optional(),
                    photo_url: photoUrlSchema.optional(),
                    assigned_user_id: z.string().uuid().optional().describe("UUID of the family member responsible for buying it (optional)."),
                    status: z.enum(ITEM_STATUSES).default("planned").describe("planned (default) or bought."),
                    priority: z.enum(ITEM_PRIORITIES).default("medium").describe("low | medium (default) | high."),
                    response_format: responseFormatSchema,
                })
                .strict(),
            annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
        },
        async ({ room, quantity, rooms, response_format, ...fields }) => {
            try {
                let placement: { allocations: AllocationInput[] } | { room_id?: string; quantity?: number };
                if (rooms) {
                    if (room !== undefined || quantity !== undefined) {
                        throw new ApiError("Use either rooms[] (split across rooms) or room + quantity (one room), not both.");
                    }
                    placement = { allocations: await resolveAllocations(client, rooms) };
                } else {
                    let room_id: string | undefined;
                    if (room) {
                        const ref = await resolveRoom(client, room);
                        room_id = ref.kind === "room" ? ref.room.id : undefined;
                    }
                    placement = { room_id, quantity };
                }
                const created = await client.post<Item>("/items", { ...fields, ...placement });
                return toolResult(response_format, { item: created }, () => itemResultMarkdown("Položka vytvorená", created));
            } catch (error) {
                return toolError(error);
            }
        },
    );

    server.registerTool(
        "new_home_set_item_rooms",
        {
            title: "Set item rooms and quantities",
            description: `Set in which rooms an item is and how much in each (decimal quantities, e.g. m²). This REPLACES the whole list: rooms not listed are removed from the item.

To ADD a room or change one quantity, first call new_home_get_item, take the current per-room rows, modify them, and send the full list.
Example: "daj dlažbu do kúpeľne hore 12,5 m² a dole 8 m²" → rooms = [{room:"Kúpeľňa hore",quantity:12.5},{room:"Kúpeľňa dole",quantity:8}].
"${WHOLE_HOUSE_FILTER}" / "${WHOLE_HOUSE_LABEL}" can be used for a spare/reserve amount not tied to a room.

Sending the same list again changes nothing (idempotent). Returns the updated item with allocations and totals.`,
            inputSchema: z
                .object({
                    item: itemRefSchema,
                    rooms: roomAllocationsSchema,
                    response_format: responseFormatSchema,
                })
                .strict(),
            annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
        },
        async ({ item, rooms, response_format }) => {
            try {
                const id = await resolveItemId(client, item);
                const allocations = await resolveAllocations(client, rooms);
                const updated = await client.put<Item>(`/items/${encodeURIComponent(id)}/allocations`, { allocations });
                return toolResult(response_format, { item: updated }, () => itemResultMarkdown("Miestnosti položky nastavené", updated));
            } catch (error) {
                return toolError(error);
            }
        },
    );

    server.registerTool(
        "new_home_update_item",
        {
            title: "Update item",
            description: `Change fields of an existing item. Only the fields you pass are changed; pass null to clear note / unit_price / url / assigned_user_id.

Not here:
- rooms and quantities → new_home_set_item_rooms;
- photo → new_home_set_photo;
- if the item has a selected variant, its price and url come from that variant → change them with new_home_update_variant (the app rejects unit_price/url here).

Returns the updated item.`,
            inputSchema: z
                .object({
                    item: itemRefSchema,
                    name: z.string().trim().min(1).max(255).optional().describe("New item name."),
                    note: z.string().trim().max(5000).nullable().optional().describe("New note; null clears it."),
                    unit_price: priceSchema.nullable().optional().describe("New price per unit in EUR (e.g. 24.9); null clears it."),
                    url: urlSchema.nullable().optional().describe("New product URL; null clears it."),
                    assigned_user_id: z.string().uuid().nullable().optional().describe("Responsible family member UUID; null unassigns."),
                    status: z.enum(ITEM_STATUSES).optional().describe("planned or bought (e.g. mark as bought)."),
                    priority: z.enum(ITEM_PRIORITIES).optional().describe("low | medium | high."),
                    response_format: responseFormatSchema,
                })
                .strict(),
            annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
        },
        async ({ item, response_format, ...fields }) => {
            try {
                const changes = Object.fromEntries(Object.entries(fields).filter(([, value]) => value !== undefined));
                if (Object.keys(changes).length === 0) {
                    throw new ApiError("Nothing to update — pass at least one of name, note, unit_price, url, assigned_user_id, status, priority.");
                }
                const id = await resolveItemId(client, item);
                try {
                    const updated = await client.patch<Item>(`/items/${encodeURIComponent(id)}`, changes);
                    return toolResult(response_format, { item: updated }, () => itemResultMarkdown("Položka upravená", updated));
                } catch (error) {
                    if (error instanceof ApiError && error.status === 422 && ("unit_price" in changes || "url" in changes)) {
                        throw new ApiError(
                            `${error.message}\nIf the item has a selected variant, its price and url come from that variant — change them with new_home_update_variant instead.`,
                            422,
                        );
                    }
                    throw error;
                }
            } catch (error) {
                return toolError(error);
            }
        },
    );

    server.registerTool(
        "new_home_delete_item",
        {
            title: "Delete item",
            description: `PERMANENTLY delete an item together with all its variants, votes, photos, comparison and room shares. Cannot be undone.

ALWAYS confirm with the user before calling: name the exact item (name + rooms, e.g. from new_home_get_item) and wait for an explicit yes. Never delete several items or guess which one is meant.
Requires the exact item UUID (names are not accepted). Only accounts with the delete permission (admin) may delete — others get a "Forbidden" error.

Returns { deleted: { id, name } }.`,
            inputSchema: z.object({ item_id: itemIdSchema, response_format: responseFormatSchema }).strict(),
            annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false },
        },
        async ({ item_id, response_format }) => {
            try {
                const detail = await loadItemDetail(client, item_id);
                await client.delete(`/items/${encodeURIComponent(detail.item.id)}`);
                const deleted = { id: detail.item.id, name: detail.item.name, variants_deleted: detail.variants.length };
                return toolResult(
                    response_format,
                    { deleted },
                    () =>
                        `Položka **${detail.item.name}** (${roomsSummary(detail.item)}) bola zmazaná` +
                        (detail.variants.length > 0 ? ` spolu s ${detail.variants.length} ${detail.variants.length === 1 ? "variantom" : "variantmi"}.` : "."),
                );
            } catch (error) {
                return toolError(error);
            }
        },
    );
}

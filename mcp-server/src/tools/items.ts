import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import { ITEM_PRIORITIES, ITEM_SORTS, ITEM_STATUSES, WHOLE_HOUSE_FILTER, WHOLE_HOUSE_LABEL } from "../constants.js";
import type { NewHomeApiClient } from "../services/api-client.js";
import { formatPrice, itemDetailMarkdown, itemLine, toolError, toolResult } from "../services/format.js";
import { resolveItemId, resolveRoom, searchItemsLocally } from "../services/resolvers.js";
import type { Item, ItemDetail, Paginated } from "../types.js";
import { itemRefSchema, priceSchema, responseFormatSchema, urlSchema } from "./schemas.js";

const roomSchema = z
    .string()
    .trim()
    .min(1)
    .max(255)
    .describe(
        `Room UUID, room name (case/diacritics-insensitive, e.g. "kupelna" matches "Kúpeľňa"; a unique partial name also works) or "${WHOLE_HOUSE_FILTER}" / "${WHOLE_HOUSE_LABEL}" for items without a room.`,
    );

export function registerItemTools(server: McpServer, client: NewHomeApiClient): void {
    server.registerTool(
        "new_home_list_items",
        {
            title: "List shopping-list items",
            description: `List/search items on the family shopping list ("Náš nový dom"), paginated.

Filters (all optional, combinable): room (id, name or "${WHOLE_HOUSE_FILTER}"), search (substring of the item name, case/diacritics-insensitive), status (planned = "Plánované", bought = "Kúpené").
Default order is the app's shopping order; use sort to override.

Returns: { total, page, per_page, last_page, has_more, next_page, items: [{ id, name, note, room_id, room_name, unit_price, quantity, total_price, url, assigned_user_name, status, priority, selected_variant_name, variants_count, ... }] }.
Prices are EUR numbers; total_price = unit_price × quantity. If has_more is true, call again with page = next_page.

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
                if (room) {
                    const ref = await resolveRoom(client, room);
                    roomFilter = ref.kind === "house" ? WHOLE_HOUSE_FILTER : ref.room.id;
                    roomLabel = ref.kind === "house" ? WHOLE_HOUSE_LABEL : ref.room.name;
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
                        lines.push(...result.data.map(itemLine));
                        const pageSum = result.data.reduce((sum, i) => sum + (i.total_price ?? 0), 0);
                        lines.push("", `Súčet cien na tejto strane: ${formatPrice(pageSum)}`);
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
            description: `Get full detail of one shopping-list item: item fields, all its variants (price, URL, vote count, who voted, whether it is the selected one) and the saved variant comparison (markdown text, generated_at, is_stale).

is_stale = true means variants changed after the comparison was saved — suggest regenerating it with new_home_save_variant_comparison.

Returns: { item: {...}, variants: [{ id, name, unit_price, url, vote_count, voter_names, is_my_vote, is_selected }], comparison: { text, generated_at, is_stale } | null }`,
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

Only name is required. room accepts a room name or id (omit or "${WHOLE_HOUSE_FILTER}" = "${WHOLE_HOUSE_LABEL}", no room). Prices are EUR numbers per piece (e.g. 1299.99); total = unit_price × quantity is computed by the app.
Photos cannot be uploaded through this tool.

Returns the created item: { id, name, room_name, unit_price, quantity, total_price, status, priority, ... }`,
            inputSchema: z
                .object({
                    name: z.string().trim().min(1).max(255).describe("Item name, e.g. 'Rohová sedačka'."),
                    room: roomSchema.optional(),
                    note: z.string().trim().max(5000).optional().describe("Free-text note."),
                    unit_price: priceSchema.optional(),
                    quantity: z.number().int().min(1).max(9999).default(1).describe("How many pieces (default 1)."),
                    url: urlSchema.optional(),
                    assigned_user_id: z.string().uuid().optional().describe("UUID of the family member responsible for buying it (optional)."),
                    status: z.enum(ITEM_STATUSES).default("planned").describe("planned (default) or bought."),
                    priority: z.enum(ITEM_PRIORITIES).default("medium").describe("low | medium (default) | high."),
                    response_format: responseFormatSchema,
                })
                .strict(),
            annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
        },
        async ({ room, response_format, ...fields }) => {
            try {
                let room_id: string | undefined;
                if (room) {
                    const ref = await resolveRoom(client, room);
                    room_id = ref.kind === "room" ? ref.room.id : undefined;
                }
                const created = await client.post<Item>("/items", { ...fields, room_id });
                return toolResult(response_format, { item: created }, () => `Položka vytvorená:\n\n${itemLine(created)}`);
            } catch (error) {
                return toolError(error);
            }
        },
    );
}

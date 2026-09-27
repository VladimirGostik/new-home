import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import { ApiError, type NewHomeApiClient } from "../services/api-client.js";
import { formatPrice, itemDetailMarkdown, toolError, toolResult, totalForUnitPrice } from "../services/format.js";
import { loadItemDetail, resolveItemId, resolveVariant } from "../services/resolvers.js";
import type { ItemDetail, ItemVariant, VariantComparison } from "../types.js";
import {
    itemIdSchema,
    itemRefSchema,
    photoUrlSchema,
    priceSchema,
    responseFormatSchema,
    urlSchema,
    variantIdSchema,
    variantRefSchema,
} from "./schemas.js";

const itemPath = (itemId: string): string => `/items/${encodeURIComponent(itemId)}`;
const variantPath = (itemId: string, variantId: string): string => `${itemPath(itemId)}/variants/${encodeURIComponent(variantId)}`;

export const COMPARISON_MAX_LENGTH = 20_000;

export function registerVariantTools(server: McpServer, client: NewHomeApiClient): void {
    server.registerTool(
        "new_home_add_item_variant",
        {
            title: "Add item variant",
            description: `Add a candidate variant (a concrete product option, e.g. a specific sofa model from a shop) to an existing item. The family then votes on variants in the app.
Every call creates a NEW variant — check existing variants with new_home_get_item first to avoid duplicates.

Voting and selecting the final variant happen in the app (not via this server).
Optional photo_url: a DIRECT image URL (jpg/png/webp, e.g. the shop's og:image), not the product page — the app downloads it.
Adding a variant marks an existing variant comparison as stale.

Returns the created variant: { id, name, unit_price, url, vote_count, voter_names, is_my_vote, is_selected }`,
            inputSchema: z
                .object({
                    item: itemRefSchema,
                    name: z.string().trim().min(1).max(255).describe("Variant name, e.g. 'IKEA KIVIK 3-miestna, sivá'."),
                    unit_price: priceSchema.optional(),
                    url: urlSchema.optional(),
                    photo_url: photoUrlSchema.optional(),
                    response_format: responseFormatSchema,
                })
                .strict(),
            annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
        },
        async ({ item, response_format, ...fields }) => {
            try {
                const id = await resolveItemId(client, item);
                const variant = await client.post<ItemVariant>(`/items/${encodeURIComponent(id)}/variants`, fields);
                return toolResult(
                    response_format,
                    { item_id: id, variant },
                    () =>
                        `Varianta pridaná: **${variant.name}** · ${formatPrice(variant.unit_price)}${variant.url ? ` · ${variant.url}` : ""}${variant.photo_url ? ` · fotka: ${variant.photo_url}` : ""} — id \`${variant.id}\``,
                );
            } catch (error) {
                return toolError(error);
            }
        },
    );

    server.registerTool(
        "new_home_save_variant_comparison",
        {
            title: "Save variant comparison",
            description: `Save (overwrite) the written comparison of an item's variants, shown to the family in the app next to the voting. Requires the item to have at least 2 variants.

REQUIRED WORKFLOW — do all steps before calling this tool:
1. Call new_home_get_item for the item and read ALL variants (names, unit prices, URLs, votes) plus the item's quantity and note.
2. Compare EVERY variant: unit price and total price for the item's (possibly decimal, per-room) quantity, value for money, key parameters (dimensions, material, warranty, delivery, availability), pros and cons. If variants have URLs and you have web tools (e.g. WebFetch/WebSearch), open them to get real specs and current prices; say explicitly when information could not be verified.
3. Write the comparison in SLOVAK, as Markdown: a short intro, a comparison table (variant | cena/ks | spolu | hlavné parametre), "Výhody / Nevýhody" per variant, the family's current votes if any, and end with a clear "## Odporúčanie" naming ONE recommended variant and why (mention a runner-up / when another choice makes sense).
4. Call this tool with the full Markdown text.

This overwrites any previous comparison (idempotent) and clears its stale flag.

Returns: { text, generated_at, is_stale }`,
            inputSchema: z
                .object({
                    item: itemRefSchema,
                    text: z
                        .string()
                        .trim()
                        .min(1)
                        .max(COMPARISON_MAX_LENGTH)
                        .describe(`The complete comparison in Slovak Markdown (max ${COMPARISON_MAX_LENGTH} characters).`),
                    response_format: responseFormatSchema,
                })
                .strict(),
            annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
        },
        async ({ item, text, response_format }) => {
            try {
                const id = await resolveItemId(client, item);
                const saved = await client.put<VariantComparison>(`/items/${encodeURIComponent(id)}/variant-comparison`, { text });
                return toolResult(
                    response_format,
                    { item_id: id, comparison: saved },
                    () => `Porovnanie uložené (${saved.generated_at}, ${saved.text.length} znakov). Rodina ho uvidí v detaile položky v aplikácii.`,
                );
            } catch (error) {
                return toolError(error);
            }
        },
    );

    server.registerTool(
        "new_home_update_variant",
        {
            title: "Update variant",
            description: `Change a variant's name, unit price (EUR) or product URL. Only the fields you pass are changed; pass null to clear unit_price / url.
If it is the selected variant, the item's price follows automatically. Changing a variant marks an existing comparison as stale. Photo → new_home_set_photo.

Returns the updated variant.`,
            inputSchema: z
                .object({
                    item: itemRefSchema,
                    variant: variantRefSchema,
                    name: z.string().trim().min(1).max(255).optional().describe("New variant name."),
                    unit_price: priceSchema.nullable().optional().describe("New price per unit in EUR; null clears it."),
                    url: urlSchema.nullable().optional().describe("New product URL; null clears it."),
                    response_format: responseFormatSchema,
                })
                .strict(),
            annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
        },
        async ({ item, variant, response_format, ...fields }) => {
            try {
                const changes = Object.fromEntries(Object.entries(fields).filter(([, value]) => value !== undefined));
                if (Object.keys(changes).length === 0) {
                    throw new ApiError("Nothing to update — pass at least one of name, unit_price, url.");
                }
                const detail = await loadItemDetail(client, item);
                const target = resolveVariant(detail, variant);
                const updated = await client.patch<ItemVariant>(variantPath(detail.item.id, target.id), changes);
                return toolResult(
                    response_format,
                    { item_id: detail.item.id, variant: updated },
                    () =>
                        `Varianta upravená: **${updated.name}** · ${formatPrice(updated.unit_price)}` +
                        (detail.item.quantity !== 1 && updated.unit_price !== null
                            ? ` (spolu ${formatPrice(totalForUnitPrice(updated.unit_price, detail.item))})`
                            : "") +
                        `${updated.url ? ` · ${updated.url}` : ""}${updated.is_selected ? " · vybraná (cena položky sa zmenila tiež)" : ""} — id \`${updated.id}\``,
                );
            } catch (error) {
                return toolError(error);
            }
        },
    );

    server.registerTool(
        "new_home_delete_variant",
        {
            title: "Delete variant",
            description: `PERMANENTLY delete one variant of an item (with its votes and photo). Cannot be undone. If it was the selected variant, the item has no selected variant afterwards.

ALWAYS confirm with the user before calling: name the item and the exact variant (name + price, e.g. from new_home_get_item) and wait for an explicit yes.
Requires the exact item UUID and variant UUID (names are not accepted). Only accounts with the delete permission (admin) may delete.

Returns { deleted: { id, name }, item_id }.`,
            inputSchema: z.object({ item_id: itemIdSchema, variant_id: variantIdSchema, response_format: responseFormatSchema }).strict(),
            annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false },
        },
        async ({ item_id, variant_id, response_format }) => {
            try {
                const detail = await loadItemDetail(client, item_id);
                const target = resolveVariant(detail, variant_id);
                await client.delete(variantPath(detail.item.id, target.id));
                return toolResult(
                    response_format,
                    { item_id: detail.item.id, deleted: { id: target.id, name: target.name } },
                    () =>
                        `Varianta **${target.name}** položky **${detail.item.name}** bola zmazaná.` +
                        (target.is_selected ? " Bola vybraná — položka teraz nemá vybranú variantu." : ""),
                );
            } catch (error) {
                return toolError(error);
            }
        },
    );

    server.registerTool(
        "new_home_select_variant",
        {
            title: "Select variant",
            description: `Mark one variant as the chosen one for the item (the family's decision). The item then takes over that variant's price, URL and photo.
Only do this when the user explicitly asks (e.g. "vyberte KIVIK", or after they agree with a recommendation) — voting stays in the app.
Selecting the already selected variant changes nothing (idempotent).

Returns the item detail (item, variants, comparison).`,
            inputSchema: z.object({ item: itemRefSchema, variant: variantRefSchema, response_format: responseFormatSchema }).strict(),
            annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
        },
        async ({ item, variant, response_format }) => {
            try {
                const detail = await loadItemDetail(client, item);
                const target = resolveVariant(detail, variant);
                const result = await client.post<ItemDetail>(`${variantPath(detail.item.id, target.id)}/select`, {});
                return toolResult(response_format, { ...result }, () => `Vybraná varianta: **${target.name}**\n\n${itemDetailMarkdown(result)}`);
            } catch (error) {
                return toolError(error);
            }
        },
    );

    server.registerTool(
        "new_home_unselect_variant",
        {
            title: "Unselect variant",
            description: `Clear the selected variant of an item (back to "not decided yet"). Variants and votes stay. Only when the user asks.
Note: the item's price, url and photo came from the selected variant, so the app CLEARS them — tell the user; set a price again with new_home_update_item if needed.
Calling it when nothing is selected changes nothing (idempotent).

Returns the item detail (item, variants, comparison).`,
            inputSchema: z.object({ item: itemRefSchema, response_format: responseFormatSchema }).strict(),
            annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
        },
        async ({ item, response_format }) => {
            try {
                const id = await resolveItemId(client, item);
                const result = await client.deleteWithBody<ItemDetail>(`${itemPath(id)}/selected-variant`);
                return toolResult(
                    response_format,
                    { ...result },
                    () => `Výber varianty zrušený (cena, odkaz a fotka položky boli vymazané).\n\n${itemDetailMarkdown(result)}`,
                );
            } catch (error) {
                return toolError(error);
            }
        },
    );
}

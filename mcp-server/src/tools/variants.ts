import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import type { NewHomeApiClient } from "../services/api-client.js";
import { formatPrice, toolError, toolResult } from "../services/format.js";
import { resolveItemId } from "../services/resolvers.js";
import type { ItemVariant, VariantComparison } from "../types.js";
import { itemRefSchema, priceSchema, responseFormatSchema, urlSchema } from "./schemas.js";

export const COMPARISON_MAX_LENGTH = 20_000;

export function registerVariantTools(server: McpServer, client: NewHomeApiClient): void {
    server.registerTool(
        "new_home_add_item_variant",
        {
            title: "Add item variant",
            description: `Add a candidate variant (a concrete product option, e.g. a specific sofa model from a shop) to an existing item. The family then votes on variants in the app.
Every call creates a NEW variant — check existing variants with new_home_get_item first to avoid duplicates.

Voting and selecting the final variant happen in the app (not via this server). Photos cannot be uploaded here.
Adding a variant marks an existing variant comparison as stale.

Returns the created variant: { id, name, unit_price, url, vote_count, voter_names, is_my_vote, is_selected }`,
            inputSchema: z
                .object({
                    item: itemRefSchema,
                    name: z.string().trim().min(1).max(255).describe("Variant name, e.g. 'IKEA KIVIK 3-miestna, sivá'."),
                    unit_price: priceSchema.optional(),
                    url: urlSchema.optional(),
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
                        `Varianta pridaná: **${variant.name}** · ${formatPrice(variant.unit_price)}${variant.url ? ` · ${variant.url}` : ""} — id \`${variant.id}\``,
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
2. Compare EVERY variant: unit price and total price for the item's quantity, value for money, key parameters (dimensions, material, warranty, delivery, availability), pros and cons. If variants have URLs and you have web tools (e.g. WebFetch/WebSearch), open them to get real specs and current prices; say explicitly when information could not be verified.
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
}

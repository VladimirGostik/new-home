import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import { ApiError, type NewHomeApiClient } from "../services/api-client.js";
import { toolError, toolResult } from "../services/format.js";
import { resolveItemId, resolveVariant } from "../services/resolvers.js";
import type { Item, ItemDetail, ItemVariant } from "../types.js";
import { itemRefSchema, photoUrlSchema, responseFormatSchema } from "./schemas.js";

export function registerPhotoTools(server: McpServer, client: NewHomeApiClient): void {
    server.registerTool(
        "new_home_set_photo",
        {
            title: "Set item or variant photo",
            description: `Set (replace) the photo of an existing item or of one of its variants from a DIRECT image URL. The app downloads and stores the image.

photo_url must point to the image itself (jpg/png/webp) — e.g. the product image from the shop page (og:image meta tag or the main gallery image), NOT the product page URL. If you only have the product page, fetch it with your web tools and extract the image URL first.

Rules:
- Give variant (name or id) to set a variant's photo. If that variant is the selected one, the app mirrors it to the item automatically.
- Without variant, the item's own photo is set — but only when the item has NO selected variant. If it has one, the item photo comes from the selected variant, so set the photo on that variant instead.

Replacing with the same URL is harmless (idempotent). Errors on photo_url (download failed, not allowed, unsupported type, too large) come back as validation errors — try a different direct image URL.

Returns the updated item ({ item }) or variant ({ variant }) including photo_url / photo_thumb_url.`,
            inputSchema: z
                .object({
                    item: itemRefSchema,
                    variant: z
                        .string()
                        .trim()
                        .min(1)
                        .max(255)
                        .optional()
                        .describe("Variant UUID or name (case/diacritics-insensitive, unique partial match ok). Omit to set the item's own photo."),
                    photo_url: photoUrlSchema,
                    response_format: responseFormatSchema,
                })
                .strict(),
            annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: true },
        },
        async ({ item, variant, photo_url, response_format }) => {
            try {
                const itemId = await resolveItemId(client, item);
                const detail = await client.get<ItemDetail>(`/items/${encodeURIComponent(itemId)}`);

                if (variant) {
                    const target = resolveVariant(detail, variant);
                    const updated = await client.put<ItemVariant>(
                        `/items/${encodeURIComponent(itemId)}/variants/${encodeURIComponent(target.id)}/photo`,
                        { photo_url },
                    );
                    return toolResult(
                        response_format,
                        { item_id: itemId, variant: updated },
                        () =>
                            `Fotka variantu **${updated.name}** (položka ${detail.item.name}) nastavená: ${updated.photo_url ?? photo_url}` +
                            (updated.is_selected ? "\nJe to vybraná varianta, takže fotku preberie aj položka." : ""),
                    );
                }

                if (detail.item.selected_variant_id) {
                    const selected = detail.variants.find((v) => v.id === detail.item.selected_variant_id);
                    throw new ApiError(
                        `Item "${detail.item.name}" has a selected variant ("${selected?.name ?? detail.item.selected_variant_name ?? detail.item.selected_variant_id}", id ${detail.item.selected_variant_id}); ` +
                            "its photo comes from that variant. Call new_home_set_photo again with variant set to that variant (or another variant) instead.",
                    );
                }

                let updated: Item;
                try {
                    updated = await client.put<Item>(`/items/${encodeURIComponent(itemId)}/photo`, { photo_url });
                } catch (error) {
                    // Race: a variant got selected in the app meanwhile. The API answers 422 on photo_url with a
                    // Slovak message (no machine-readable code), so re-check the item to explain what to do.
                    if (error instanceof ApiError && error.status === 422) {
                        const fresh = await client.get<ItemDetail>(`/items/${encodeURIComponent(itemId)}`).catch(() => null);
                        if (fresh?.item.selected_variant_id) {
                            throw new ApiError(
                                `${error.message}\nItem "${fresh.item.name}" now has a selected variant ("${fresh.item.selected_variant_name ?? fresh.item.selected_variant_id}", id ${fresh.item.selected_variant_id}); ` +
                                    "set the photo on that variant with new_home_set_photo (variant parameter) instead.",
                                422,
                            );
                        }
                    }
                    throw error;
                }
                return toolResult(
                    response_format,
                    { item: updated },
                    () => `Fotka položky **${updated.name}** nastavená: ${updated.photo_url ?? photo_url}`,
                );
            } catch (error) {
                return toolError(error);
            }
        },
    );
}

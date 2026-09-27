import { z } from "zod";
import { ResponseFormat, WHOLE_HOUSE_FILTER, WHOLE_HOUSE_LABEL } from "../constants.js";

export const responseFormatSchema = z
    .enum(ResponseFormat)
    .default(ResponseFormat.MARKDOWN)
    .describe("Output format: 'markdown' (default, human-readable, Slovak labels) or 'json' (raw API data for further processing).");

export const itemRefSchema = z
    .string()
    .trim()
    .min(1)
    .max(255)
    .describe(
        "Item UUID (preferred, e.g. from new_home_list_items) or the item's name. A name must match exactly one item (case/diacritics-insensitive), otherwise an error lists the candidates.",
    );

export const priceSchema = z
    .number()
    .min(0)
    .max(99_999_999.99)
    .describe("Price per piece in EUR as a plain number, e.g. 249.9 (not cents, no currency symbol).");

export const photoUrlSchema = z
    .string()
    .trim()
    .url()
    .max(2048)
    .refine((value) => /^https?:\/\//i.test(value), "photo_url must start with http:// or https://")
    .describe(
        "DIRECT image URL (jpg/png/webp) that the app downloads and stores, e.g. the product image from the shop page (its og:image meta tag or the main gallery <img src>). NOT the product page URL — that belongs in `url`.",
    );

export const urlSchema = z.string().trim().url().max(2048).describe("Product/shop URL (must be a valid http(s) URL).");

export const roomSchema = z
    .string()
    .trim()
    .min(1)
    .max(255)
    .describe(
        `Room UUID, room name (case/diacritics-insensitive, e.g. "kupelna" matches "Kúpeľňa"; a unique partial name also works) or "${WHOLE_HOUSE_FILTER}" / "${WHOLE_HOUSE_LABEL}" for the whole house (no specific room).`,
    );

const hasAtMostTwoDecimals = (value: number): boolean => Math.abs(value * 100 - Math.round(value * 100)) < 1e-6;

/** Decimal quantity per room (e.g. 12.5 m², 3 pcs). */
export const quantitySchema = z
    .number()
    .min(0.01)
    .max(9999.99)
    .refine(hasAtMostTwoDecimals, "quantity may have at most 2 decimal places")
    .describe("Quantity as a number with up to 2 decimals, e.g. 3 (pieces) or 12.5 (m²). Use a dot, not a comma.");

export const roomAllocationsSchema = z
    .array(
        z
            .object({
                room: roomSchema,
                quantity: quantitySchema,
            })
            .strict(),
    )
    .min(1)
    .max(50)
    .describe(
        `Per-room quantities, e.g. [{"room":"Kúpeľňa hore","quantity":12.5},{"room":"Kúpeľňa dole","quantity":8}]. Each room at most once; "${WHOLE_HOUSE_FILTER}" = ${WHOLE_HOUSE_LABEL} (e.g. a spare/reserve amount).`,
    );

export const variantRefSchema = z
    .string()
    .trim()
    .min(1)
    .max(255)
    .describe("Variant UUID (preferred, from new_home_get_item) or variant name (case/diacritics-insensitive; a unique partial name also works).");

export const itemIdSchema = z
    .string()
    .trim()
    .uuid()
    .describe("Exact item UUID (from new_home_list_items / new_home_get_item). Names are not accepted for destructive actions.");

export const variantIdSchema = z
    .string()
    .trim()
    .uuid()
    .describe("Exact variant UUID (from new_home_get_item). Names are not accepted for destructive actions.");

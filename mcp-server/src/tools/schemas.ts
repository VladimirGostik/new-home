import { z } from "zod";
import { ResponseFormat } from "../constants.js";

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

export const urlSchema = z.string().trim().url().max(2048).describe("Product/shop URL (must be a valid http(s) URL).");

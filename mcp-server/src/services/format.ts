import type { CallToolResult } from "@modelcontextprotocol/sdk/types.js";
import { CHARACTER_LIMIT, PRIORITY_LABELS, ResponseFormat, STATUS_LABELS, WHOLE_HOUSE_LABEL } from "../constants.js";
import type { Item, ItemAllocation, ItemDetail, ItemVariant, Room } from "../types.js";
import { ApiError } from "./api-client.js";

const eur = new Intl.NumberFormat("sk-SK", { style: "currency", currency: "EUR" });

const decimal = new Intl.NumberFormat("sk-SK", { maximumFractionDigits: 2 });

/** Slovak decimal formatting: 12.5 → "12,5", 1234 → "1 234". */
export function formatQuantity(value: number): string {
    return decimal.format(value);
}

function allocationRoom(allocation: ItemAllocation): string {
    return allocation.room_name ?? WHOLE_HOUSE_LABEL;
}

/** Allocations from the API, or a single synthetic row for older backends without them. */
export function allocationsOf(item: Item): ItemAllocation[] {
    if (item.allocations && item.allocations.length > 0) {
        return item.allocations;
    }
    return [
        {
            id: "",
            room_id: item.room_id,
            room_name: item.room_name,
            quantity: item.quantity,
            line_total: item.total_price,
        },
    ];
}

/** Σ round(unit × qty_i, 2) — same rounding the app uses (per-room line totals). */
export function totalForUnitPrice(unitPrice: number | null, item: Item): number | null {
    if (unitPrice === null) return null;
    return allocationsOf(item).reduce((sum, a) => sum + Math.round(unitPrice * a.quantity * 100) / 100, 0);
}

/** "Kúpeľňa 12,5 + Celý dom 2" (multi-room) or "Kúpeľňa" / "Kúpeľňa 3" (single room). */
export function roomsSummary(item: Item): string {
    const allocations = allocationsOf(item);
    if (allocations.length === 1 && allocations[0]) {
        return allocationRoom(allocations[0]);
    }
    return allocations.map((a) => `${allocationRoom(a)} ${formatQuantity(a.quantity)}`).join(" + ");
}

export function formatPrice(value: number | null | undefined): string {
    return value === null || value === undefined ? "—" : eur.format(value);
}

function truncate(text: string): string {
    if (text.length <= CHARACTER_LIMIT) {
        return text;
    }
    return `${text.slice(0, CHARACTER_LIMIT)}\n\n…[truncated at ${CHARACTER_LIMIT} characters — narrow the query (search/room/status) or use a smaller per_page]`;
}

/** Build a tool result with both a text rendering and structured content. */
export function toolResult(format: ResponseFormat, structured: Record<string, unknown>, markdown: () => string): CallToolResult {
    const text = format === ResponseFormat.JSON ? JSON.stringify(structured, null, 2) : markdown();
    return {
        content: [{ type: "text", text: truncate(text) }],
        structuredContent: structured,
    };
}

export function toolError(error: unknown): CallToolResult {
    const message =
        error instanceof ApiError ? error.message : `Unexpected error: ${error instanceof Error ? error.message : String(error)}`;
    return { isError: true, content: [{ type: "text", text: `Error: ${message}` }] };
}

export function roomsMarkdown(rooms: Room[]): string {
    const lines = ["# Miestnosti", ""];
    for (const room of rooms) {
        lines.push(`- **${room.name}** — id \`${room.id}\``);
    }
    lines.push(`- **${WHOLE_HOUSE_LABEL}** — položky bez miestnosti (filter \`house\`)`);
    return lines.join("\n");
}

function variantsLabel(count: number): string {
    if (count === 1) return "1 varianta";
    return count >= 2 && count <= 4 ? `${count} varianty` : `${count} variantov`;
}

/**
 * One list line. With focusRoomId (list filtered by a room; null = Celý dom) the share
 * of that room is appended for multi-room items.
 */
export function itemLine(item: Item, focusRoomId?: string | null): string {
    const price =
        item.unit_price === null
            ? "cena —"
            : item.quantity !== 1
              ? `${formatQuantity(item.quantity)} × ${formatPrice(item.unit_price)} = ${formatPrice(item.total_price)}`
              : formatPrice(item.unit_price);
    const parts = [`**${item.name}**`, roomsSummary(item), price];
    const allocations = allocationsOf(item);
    if (focusRoomId !== undefined && allocations.length > 1) {
        const share = allocations.find((a) => a.room_id === focusRoomId);
        if (share) {
            parts.push(`z toho tu ${formatQuantity(share.quantity)}${share.line_total !== null ? ` = ${formatPrice(share.line_total)}` : ""}`);
        }
    }
    parts.push(
        STATUS_LABELS[item.status] ?? item.status,
        `priorita ${PRIORITY_LABELS[item.priority] ?? item.priority}`,
    );
    if (item.variants_count > 0) {
        parts.push(`${variantsLabel(item.variants_count)}${item.selected_variant_name ? `, vybraná: ${item.selected_variant_name}` : ""}`);
    }
    if (item.assigned_user_name) {
        parts.push(`zodpovedný: ${item.assigned_user_name}`);
    }
    return `- ${parts.join(" · ")} — id \`${item.id}\``;
}

function variantPriceCell(variant: ItemVariant, item: Item): string {
    if (variant.unit_price === null) {
        return "—";
    }
    return item.quantity !== 1
        ? `${formatPrice(variant.unit_price)} (spolu ${formatPrice(totalForUnitPrice(variant.unit_price, item))})`
        : formatPrice(variant.unit_price);
}

function escapeCell(value: string): string {
    return value.replace(/\|/g, "\\|").replace(/\n/g, " ");
}

export function itemDetailMarkdown(detail: ItemDetail): string {
    const { item, variants, comparison } = detail;
    const lines = [
        `# ${item.name}`,
        "",
        `- **ID:** \`${item.id}\``,
        `- **Miestnosť:** ${roomsSummary(item)}`,
        `- **Cena/jednotku:** ${formatPrice(item.unit_price)} · **Množstvo:** ${formatQuantity(item.quantity)} · **Spolu:** ${formatPrice(item.total_price)}`,
        `- **Stav:** ${STATUS_LABELS[item.status] ?? item.status} · **Priorita:** ${PRIORITY_LABELS[item.priority] ?? item.priority}`,
    ];
    if (item.assigned_user_name) lines.push(`- **Zodpovedný:** ${item.assigned_user_name}`);
    if (item.url) lines.push(`- **Odkaz:** ${item.url}`);
    if (item.note) lines.push(`- **Poznámka:** ${item.note}`);
    if (item.photo_url) lines.push(`- **Fotka:** ${item.photo_url}`);
    lines.push(`- **Vybraná varianta:** ${item.selected_variant_name ?? "zatiaľ žiadna"}`);

    const allocations = allocationsOf(item);
    if (allocations.length > 1) {
        lines.push("", "## Rozdelenie po miestnostiach", "", "| Miestnosť | Množstvo | Spolu |", "|---|---|---|");
        for (const a of allocations) {
            lines.push(`| ${escapeCell(allocationRoom(a))} | ${formatQuantity(a.quantity)} | ${formatPrice(a.line_total)} |`);
        }
        lines.push(`| **Spolu** | **${formatQuantity(item.quantity)}** | **${formatPrice(item.total_price)}** |`);
    }

    lines.push("", `## Varianty (${variants.length})`, "");
    if (variants.length === 0) {
        lines.push("Zatiaľ žiadne varianty. Pridaj ich cez new_home_add_item_variant.");
    } else {
        lines.push("| Varianta | Cena/jedn. | Hlasy | Hlasovali | Vybraná | Odkaz | Fotka | ID |", "|---|---|---|---|---|---|---|---|");
        for (const v of variants) {
            lines.push(
                `| ${escapeCell(v.name)} | ${variantPriceCell(v, item)} | ${v.vote_count}${v.is_my_vote ? " (aj môj)" : ""} | ${escapeCell(v.voter_names.join(", ") || "—")} | ${v.is_selected ? "✅" : ""} | ${v.url ?? "—"} | ${v.photo_url ? `[foto](${v.photo_url})` : "—"} | \`${v.id}\` |`,
            );
        }
    }

    lines.push("", "## Porovnanie variantov", "");
    if (!comparison) {
        lines.push(variants.length >= 2 ? "Zatiaľ neuložené. Dá sa vytvoriť cez new_home_save_variant_comparison." : "Nie je k dispozícii (potrebné aspoň 2 varianty).");
    } else {
        lines.push(
            `_Uložené: ${comparison.generated_at || "neznámo"}${comparison.is_stale ? " · ⚠️ ZASTARANÉ — varianty sa od porovnania zmenili, odporúča sa ho pregenerovať" : ""}_`,
            "",
            comparison.text,
        );
    }
    return lines.join("\n");
}

/** Short confirmation for a created/updated item: list line + per-room breakdown + photo. */
export function itemResultMarkdown(title: string, item: Item): string {
    const lines = [`${title}:`, "", itemLine(item)];
    const allocations = allocationsOf(item);
    if (allocations.length > 1) {
        lines.push("", "Rozdelenie po miestnostiach:");
        for (const a of allocations) {
            lines.push(`- ${allocationRoom(a)}: ${formatQuantity(a.quantity)}${a.line_total !== null ? ` = ${formatPrice(a.line_total)}` : ""}`);
        }
    }
    if (item.photo_url) lines.push("", `Fotka: ${item.photo_url}`);
    return lines.join("\n");
}

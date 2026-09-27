import type { CallToolResult } from "@modelcontextprotocol/sdk/types.js";
import { CHARACTER_LIMIT, PRIORITY_LABELS, ResponseFormat, STATUS_LABELS, WHOLE_HOUSE_LABEL } from "../constants.js";
import type { Item, ItemDetail, ItemVariant, Room } from "../types.js";
import { ApiError } from "./api-client.js";

const eur = new Intl.NumberFormat("sk-SK", { style: "currency", currency: "EUR" });

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

export function itemLine(item: Item): string {
    const price =
        item.unit_price === null
            ? "cena —"
            : item.quantity > 1
              ? `${item.quantity} × ${formatPrice(item.unit_price)} = ${formatPrice(item.total_price)}`
              : formatPrice(item.unit_price);
    const parts = [
        `**${item.name}**`,
        item.room_name ?? WHOLE_HOUSE_LABEL,
        price,
        STATUS_LABELS[item.status] ?? item.status,
        `priorita ${PRIORITY_LABELS[item.priority] ?? item.priority}`,
    ];
    if (item.variants_count > 0) {
        parts.push(`${variantsLabel(item.variants_count)}${item.selected_variant_name ? `, vybraná: ${item.selected_variant_name}` : ""}`);
    }
    if (item.assigned_user_name) {
        parts.push(`zodpovedný: ${item.assigned_user_name}`);
    }
    return `- ${parts.join(" · ")} — id \`${item.id}\``;
}

function variantPriceCell(variant: ItemVariant, quantity: number): string {
    if (variant.unit_price === null) {
        return "—";
    }
    return quantity > 1
        ? `${formatPrice(variant.unit_price)} (spolu ${formatPrice(Math.round(variant.unit_price * quantity * 100) / 100)})`
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
        `- **Miestnosť:** ${item.room_name ?? WHOLE_HOUSE_LABEL}`,
        `- **Cena/ks:** ${formatPrice(item.unit_price)} · **Množstvo:** ${item.quantity} · **Spolu:** ${formatPrice(item.total_price)}`,
        `- **Stav:** ${STATUS_LABELS[item.status] ?? item.status} · **Priorita:** ${PRIORITY_LABELS[item.priority] ?? item.priority}`,
    ];
    if (item.assigned_user_name) lines.push(`- **Zodpovedný:** ${item.assigned_user_name}`);
    if (item.url) lines.push(`- **Odkaz:** ${item.url}`);
    if (item.note) lines.push(`- **Poznámka:** ${item.note}`);
    lines.push(`- **Vybraná varianta:** ${item.selected_variant_name ?? "zatiaľ žiadna"}`);

    lines.push("", `## Varianty (${variants.length})`, "");
    if (variants.length === 0) {
        lines.push("Zatiaľ žiadne varianty. Pridaj ich cez new_home_add_item_variant.");
    } else {
        lines.push("| Varianta | Cena/ks | Hlasy | Hlasovali | Vybraná | Odkaz | ID |", "|---|---|---|---|---|---|---|");
        for (const v of variants) {
            lines.push(
                `| ${escapeCell(v.name)} | ${variantPriceCell(v, item.quantity)} | ${v.vote_count}${v.is_my_vote ? " (aj môj)" : ""} | ${escapeCell(v.voter_names.join(", ") || "—")} | ${v.is_selected ? "✅" : ""} | ${v.url ?? "—"} | \`${v.id}\` |`,
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

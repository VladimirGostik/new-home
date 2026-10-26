import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import { EXPENSE_TYPES } from "../constants.js";
import { ApiError, type NewHomeApiClient } from "../services/api-client.js";
import {
    expenseLine,
    expenseResultMarkdown,
    expenseTotalsMarkdown,
    formatPrice,
    paymentSummaryMarkdown,
    toolError,
    toolResult,
} from "../services/format.js";
import { resolveItemId } from "../services/resolvers.js";
import type { Expense, ExpensePage, ExpenseResult, ItemPayments } from "../types.js";
import { amountSchema, expenseIdSchema, itemRefSchema, paidAtSchema, responseFormatSchema } from "./schemas.js";

const noteSchema = z.string().trim().max(255).optional().describe("Optional short note, e.g. 'záloha' or 'doplatok' (max 255 characters).");

const monthSchema = z
    .string()
    .trim()
    .regex(/^\d{4}-(0[1-9]|1[0-2])$/, "month must be YYYY-MM")
    .describe("Whole calendar month as YYYY-MM, e.g. 2026-10. Do not combine with from/to.");

function monthRange(month: string): { from: string; to: string } {
    const [year, mon] = month.split("-").map(Number) as [number, number];
    const lastDay = new Date(Date.UTC(year, mon, 0)).getUTCDate();
    return { from: `${month}-01`, to: `${month}-${String(lastDay).padStart(2, "0")}` };
}

export function registerExpenseTools(server: McpServer, client: NewHomeApiClient): void {
    server.registerTool(
        "new_home_add_payment",
        {
            title: "Add item payment",
            description: `Record a payment (záloha, doplatok, instalment) on a shopping-list item. The payment is also an expense in the Výdavky ledger. Every call creates a NEW payment — check new_home_list_payments first if it might already be recorded.

amount is EUR (> 0, up to 2 decimals); paid_at is YYYY-MM-DD and must not be in the future. Overpaying is allowed: the result then carries a warning and the item's overpaid > 0 — relay it to the user.

Returns: { expense: { id, item_id, amount, paid_at, note, ... }, item_summary: { total_price, paid_total, remaining, payment_status, overpaid }, warning }.`,
            inputSchema: z
                .object({ item: itemRefSchema, amount: amountSchema, paid_at: paidAtSchema, note: noteSchema, response_format: responseFormatSchema })
                .strict(),
            annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
        },
        async ({ item, amount, paid_at, note, response_format }) => {
            try {
                const id = await resolveItemId(client, item);
                const result = await client.post<ExpenseResult>(`/items/${encodeURIComponent(id)}/payments`, { amount, paid_at, note });
                return toolResult(response_format, { ...result }, () => expenseResultMarkdown("Platba zaznamenaná", result));
            } catch (error) {
                return toolError(error);
            }
        },
    );

    server.registerTool(
        "new_home_list_payments",
        {
            title: "List item payments",
            description: `List all payments recorded on one item (newest first) with the payment summary: item price, paid_total, remaining, payment_status (unpaid | partial | paid), overpaid.

Returns: { payments: [{ id, amount, paid_at, note, created_by_name, ... }], summary: { total_price, paid_total, remaining, payment_status, overpaid } }.`,
            inputSchema: z.object({ item: itemRefSchema, response_format: responseFormatSchema }).strict(),
            annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
        },
        async ({ item, response_format }) => {
            try {
                const id = await resolveItemId(client, item);
                const result = await client.get<ItemPayments>(`/items/${encodeURIComponent(id)}/payments`);
                return toolResult(response_format, { ...result }, () => {
                    const lines = [`# Platby (${result.payments.length})`, "", paymentSummaryMarkdown(result.summary), ""];
                    lines.push(...(result.payments.length === 0 ? ["Zatiaľ žiadne platby."] : result.payments.map(expenseLine)));
                    return lines.join("\n");
                });
            } catch (error) {
                return toolError(error);
            }
        },
    );

    server.registerTool(
        "new_home_delete_payment",
        {
            title: "Delete payment or expense",
            description: `PERMANENTLY delete one payment or expense (any row of the Výdavky ledger, item payment or standalone). Cannot be undone; the item's paid_total and remaining change accordingly.

ALWAYS confirm with the user before calling: name the item/expense, the amount and the date (e.g. from new_home_list_payments / new_home_list_expenses) and wait for an explicit yes. Never delete several rows or guess which one is meant.
Requires the exact payment UUID (names are not accepted).

Returns { deleted: { id, display_name, amount, paid_at } }.`,
            inputSchema: z.object({ payment_id: expenseIdSchema, response_format: responseFormatSchema }).strict(),
            annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false },
        },
        async ({ payment_id, response_format }) => {
            try {
                const expense = await client.get<Expense>(`/expenses/${encodeURIComponent(payment_id)}`);
                await client.delete(`/expenses/${encodeURIComponent(expense.id)}`);
                const deleted = { id: expense.id, display_name: expense.display_name, amount: expense.amount, paid_at: expense.paid_at };
                return toolResult(
                    response_format,
                    { deleted },
                    () => `Platba **${formatPrice(expense.amount)}** (${expense.display_name}, ${expense.paid_at}) bola zmazaná.`,
                );
            } catch (error) {
                return toolError(error);
            }
        },
    );

    server.registerTool(
        "new_home_add_expense",
        {
            title: "Add standalone expense",
            description: `Record a standalone expense in the Výdavky ledger that is NOT tied to a shopping-list item (e.g. "Notár", "Kolaudácia"). For a deposit/instalment on an item use new_home_add_payment instead. Every call creates a NEW expense.

title is required; amount is EUR (> 0, up to 2 decimals); paid_at is YYYY-MM-DD and must not be in the future.

Returns: { expense: { id, title, amount, paid_at, note, ... }, item_summary: null, warning: null }.`,
            inputSchema: z
                .object({
                    title: z.string().trim().min(1).max(255).describe("What the money was spent on, e.g. 'Notár'."),
                    amount: amountSchema,
                    paid_at: paidAtSchema,
                    note: noteSchema,
                    response_format: responseFormatSchema,
                })
                .strict(),
            annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
        },
        async ({ title, amount, paid_at, note, response_format }) => {
            try {
                const result = await client.post<ExpenseResult>("/expenses", { title, amount, paid_at, note });
                return toolResult(response_format, { ...result }, () => expenseResultMarkdown("Výdavok zaznamenaný", result));
            } catch (error) {
                return toolError(error);
            }
        },
    );

    server.registerTool(
        "new_home_list_expenses",
        {
            title: "List expenses (Výdavky)",
            description: `List the Výdavky ledger (all item payments + standalone expenses), newest first, paginated, with totals over the WHOLE filtered set.

Filters (optional, combinable): type (item = payments on items, other = standalone expenses), search (expense title or item name), month (YYYY-MM) OR from/to (YYYY-MM-DD, inclusive), item (item name or UUID; only that item's payments).

Returns: { total, page, per_page, last_page, has_more, next_page, expenses: [{ id, item_id, item_name, title, display_name, type, amount, paid_at, note, created_by_name }], totals: { total, items_total, manual_total, count } }.
totals.total = real money spent (including overpayments). If has_more is true, call again with page = next_page.`,
            inputSchema: z
                .object({
                    type: z.enum(EXPENSE_TYPES).optional().describe("item = payments on items, other = standalone expenses."),
                    search: z.string().trim().max(255).optional().describe("Substring of the expense title or item name."),
                    month: monthSchema.optional(),
                    from: paidAtSchema.optional().describe("Earliest paid_at (YYYY-MM-DD, inclusive)."),
                    to: paidAtSchema.optional().describe("Latest paid_at (YYYY-MM-DD, inclusive)."),
                    item: itemRefSchema.optional().describe("Only payments of this item (UUID or exact name)."),
                    page: z.number().int().min(1).default(1).describe("Page number (1-based)."),
                    per_page: z.number().int().min(1).max(100).default(25).describe("Rows per page (1–100, default 25)."),
                    response_format: responseFormatSchema,
                })
                .strict(),
            annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
        },
        async ({ type, search, month, from, to, item, page, per_page, response_format }) => {
            try {
                if (month && (from || to)) {
                    throw new ApiError("Use either month or from/to, not both.");
                }
                const range = month ? monthRange(month) : { from, to };
                const itemId = item ? await resolveItemId(client, item) : undefined;
                const result = await client.get<ExpensePage>("/expenses", {
                    "filter[type]": type,
                    "filter[search]": search,
                    "filter[from]": range.from,
                    "filter[to]": range.to,
                    "filter[item]": itemId,
                    page,
                    per_page,
                });

                const hasMore = result.current_page < result.last_page;
                const structured = {
                    total: result.total,
                    page: result.current_page,
                    per_page: result.per_page,
                    last_page: result.last_page,
                    has_more: hasMore,
                    next_page: hasMore ? result.current_page + 1 : null,
                    filters: { type: type ?? null, search: search ?? null, from: range.from ?? null, to: range.to ?? null, item: itemId ?? null },
                    expenses: result.data,
                    totals: result.totals,
                };

                return toolResult(response_format, structured, () => {
                    const lines = ["# Výdavky", "", expenseTotalsMarkdown(result.totals), ""];
                    if (result.data.length === 0) {
                        lines.push("Žiadne výdavky nevyhovujú filtru.");
                    } else {
                        lines.push(`Zobrazené ${result.data.length} z ${result.total} (strana ${result.current_page}/${result.last_page}).`, "");
                        lines.push(...result.data.map(expenseLine));
                        if (hasMore) lines.push("", `Ďalšia strana: page=${result.current_page + 1}`);
                    }
                    return lines.join("\n");
                });
            } catch (error) {
                return toolError(error);
            }
        },
    );
}

# Náš nový dom

Project bootstrapped from `vue-skeleton` via `/build-laravel-app`.

## Stack target

Laravel 13

## Modules

- **items** — Item model + service; shopping-list items with optional variants, voting, AI comparison; computed paid_total / remaining / payment_status / overpaid (from expenses), `payment_status` filter
- **item-allocations** — ItemAllocation model; item quantity per room (decimal) with cascade/restrict constraints, per-line rounding
- **item-variants** — ItemVariant model + service; variant proposals with voting and API partial-update
- **rooms** — Room model; household sections (kitchen, bedroom, etc.), delete merges allocations to whole house
- **expenses** — Expense model + ExpenseService; single ledger: item payments are expenses with item_id, standalone expenses have a title; month-by-month Výdavky page; item delete keeps expenses as standalone with item name snapshot as title
- **auth** — Web login (session), API login (Sanctum token) with email+IP rate limit
- **api** — JSON API routes via Sanctum authentication + precognition (PATCH/DELETE items/variants, PUT allocations, item payments + expenses endpoints)
- **mcp-server** — Standalone Node.js/TypeScript MCP server (stdio transport); 19 tools for rooms/items/variants/allocations/photos/comparison/payments/expenses (v1.3.0)

## Auth profile

profile: role-only

## Deployment Status

- **Deployed to production:** yes (Laravel Cloud: https://new-home-production-prxbss.laravel.cloud)
- **Last verified:** 2026-09-27

## Local ports

- App: http://localhost:8003
- Vite: http://localhost:5176
- Postgres: 5435 (host) → 5432
- Redis: 6382 (host) → 6379

## Lint

lint.tools: [pint, phpstan, vue-tsc, eslint, prettier]
lint.runner: docker
lint.asked: true

## Next steps

- Add business domain to `.claude/business.md` (create if missing).
- Run `/scaffold-module <name> fields=...` for each business entity.
- See `AGENTS.md` for Laravel Boost guidelines used by this stack.

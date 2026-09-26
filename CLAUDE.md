# Náš nový dom

Project bootstrapped from `vue-skeleton` via `/build-laravel-app`.

## Stack target

Laravel 13

## Auth profile

profile: role-only

## Deployment Status

- **Deployed to production:** no
- **Last verified:** 2026-09-26

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

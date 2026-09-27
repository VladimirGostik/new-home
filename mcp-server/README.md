# new-home MCP server

MCP server pre aplikáciu **Náš nový dom** – umožňuje spravovať nákupný zoznam (miestnosti → položky → varianty) priamo z Claude Code.
Samostatný Node/TypeScript balík (nie je súčasťou root pnpm workspace ani root lintu).

## Inštalácia pre rodinu (Claude Desktop, bez terminálu)

1. Nainštaluj si **Claude Desktop** zo stránky https://claude.ai/download a prihlás sa.
2. Stiahni si súbor **`new-home.mcpb`** (pošle ti ho Vlado).
3. Dvakrát naň klikni – otvorí sa Claude Desktop s ponukou na inštaláciu rozšírenia **Náš nový dom**.
   (Ak sa neotvorí: v Claude Desktop choď do **Nastavenia → Rozšírenia** a súbor tam nainštaluj / pretiahni.)
4. Vyplň **svoj vlastný** e-mail a heslo, ktorými sa prihlasuješ do aplikácie Náš nový dom.
   Každý používa **svoj** účet – nepožičiavaj si prihlasovacie údaje, aby bolo vidno, kto čo pridal a za čo hlasoval.
   Pole „Adresa aplikácie“ nechaj tak, ako je.
5. Hotovo. V novom rozhovore s Claudom môžeš písať napríklad:
   - „Vypíš mi izby.“
   - „Čo nám ešte chýba kúpiť do kúpeľne?“
   - „Porovnaj varianty pre sedačku.“
   - „Pridaj do kuchyne položku Kávovar za 350 €.“

Heslo sa ukladá bezpečne v systéme (Keychain / Správca poverení). Ak si ho zmeníš v aplikácii, zmeň ho aj v Nastaveniach → Rozšírenia → Náš nový dom.

## Build

```bash
cd mcp-server
npm install
npm run build        # výstup: mcp-server/dist/index.js
```

Vyžaduje Node 20+ a bežiacu aplikáciu (`docker compose up -d` v roote repa, http://localhost:8003).

## Premenné prostredia

| Premenná            | Popis                                             | Predvolené              |
| ------------------- | ------------------------------------------------- | ----------------------- |
| `NEW_HOME_API_URL`  | URL aplikácie                                     | `http://localhost:8003` |
| `NEW_HOME_EMAIL`    | e-mail, ktorým sa prihlasuješ do aplikácie        | –                       |
| `NEW_HOME_PASSWORD` | heslo do aplikácie                                | –                       |

Prihlasovacie údaje nikdy nezapisuj do súborov v repe. Nastav ich v shelli (napr. v `~/.zshrc`):

```bash
export NEW_HOME_EMAIL="ja@example.com"
export NEW_HOME_PASSWORD="…"
```

Server sa prihlási raz (login je limitovaný na 5 pokusov/min), token si drží v pamäti a pri 401 sa prihlási znova.

## Pripojenie v Claude Code

Server je už zaregistrovaný v `.mcp.json` v roote repa (`new-home`). Stačí:

1. zbuildovať server (vyššie),
2. mať exportované `NEW_HOME_EMAIL` a `NEW_HOME_PASSWORD`,
3. spustiť `claude` v roote repa a schváliť projektový MCP server (`/mcp` ukáže stav).

Alternatívne ručne (mimo repa):

```bash
claude mcp add new-home \
  -e NEW_HOME_API_URL=http://localhost:8003 \
  -e NEW_HOME_EMAIL="$NEW_HOME_EMAIL" -e NEW_HOME_PASSWORD="$NEW_HOME_PASSWORD" \
  -- node "/cesta/k/new-home/mcp-server/dist/index.js"
```

Ladenie: `npm run inspect` (MCP Inspector).

## Pre vývojára: nový balík `.mcpb`

```bash
cd mcp-server
npm install          # raz, kvôli dev závislostiam (tsc, mcpb CLI)
npm run pack:mcpb    # → mcp-server/new-home.mcpb
```

Skript (`scripts/pack-mcpb.mjs`) zbuilduje `dist/`, overí, že text promptu v `manifest.json` sedí so `src/prompts.ts`,
v dočasnom `.mcpb-build/` nainštaluje len produkčné závislosti, spustí `mcpb validate` a `mcpb pack`. Vývojové `node_modules` ostanú nedotknuté.
Pri novej verzii zvýš `version` v `package.json` aj `manifest.json` (musia sa zhodovať). Súbor `.mcpb` sa necommituje.
Predvolená adresa v balíku je produkcia (`user_config.api_url`); lokálny vývoj cez `.mcp.json` používa `http://localhost:8003`.

## Nástroje

| Nástroj                            | Čo robí                                                               |
| ---------------------------------- | --------------------------------------------------------------------- |
| `new_home_list_rooms`              | zoznam miestností (+ „Celý dom“ = položky bez miestnosti)             |
| `new_home_list_items`              | položky s filtrom (miestnosť podľa názvu/id/`house`, hľadanie, stav)  |
| `new_home_get_item`                | detail položky: varianty, hlasy, vybraná varianta, uložené porovnanie |
| `new_home_create_item`             | nová položka (miestnosť názvom alebo id, ceny v EUR)                  |
| `new_home_add_item_variant`        | nová varianta k položke                                               |
| `new_home_save_variant_comparison` | uloží (prepíše) porovnanie variantov v Markdowne                      |

Prompt `compare_item_variants` (argument: názov alebo id položky) prevedie Clauda celým porovnaním – načíta varianty, porovná ich (aj cez odkazy do obchodov), napíše odporúčanie po slovensky a uloží ho do aplikácie.

Fotky sa cez MCP nenahrávajú; hlasovanie a výber varianty prebiehajú v aplikácii.

## Príklady

- „Čo nám ešte chýba kúpiť do kúpeľne?“
- „Pridaj do obývačky položku Konferenčný stolík, 2 kusy po 89 €, priorita vysoká.“
- „K sedačke pridaj variantu IKEA KIVIK za 799 € s odkazom https://www.ikea.com/sk/…“
- „Porovnaj varianty sedačky a ulož odporúčanie.“ (alebo `/mcp__new-home__compare_item_variants Sedačka`)
- „Koľko ešte minieme za všetko plánované v Celom dome?“

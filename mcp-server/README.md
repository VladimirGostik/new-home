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
   - „K variante KIVIK pri sedačke daj fotku z tohto odkazu: https://…/kivik.jpg“
   - „Daj dlažbu do kúpeľne hore 12,5 m² a dole 8 m².“
   - „Označ sedačku ako kúpenú.“
   - „Zmaž položku Rohožky ku dverám.“ (Claude sa vždy najprv opýta, či naozaj)

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
| `new_home_list_rooms`              | zoznam miestností (+ „Celý dom“ = položky bez konkrétnej miestnosti)            |
| `new_home_list_items`              | položky s filtrom (miestnosť názvom/id/`house`, hľadanie, stav); podiel miestnosti |
| `new_home_get_item`                | detail: rozdelenie po miestnostiach, varianty, hlasy, vybraná varianta, porovnanie |
| `new_home_create_item`             | nová položka – jedna miestnosť + množstvo, alebo viac miestností (`rooms[]`)     |
| `new_home_set_item_rooms`          | nastaví miestnosti a desatinné množstvá položky (celý zoznam naraz)              |
| `new_home_update_item`             | úprava položky (názov, poznámka, cena, odkaz, zodpovedný, stav, priorita)        |
| `new_home_delete_item`             | natrvalo zmaže položku – **len po potvrdení** používateľom                       |
| `new_home_add_item_variant`        | nová varianta k položke                                                          |
| `new_home_update_variant`          | úprava varianty (názov, cena, odkaz)                                             |
| `new_home_delete_variant`          | natrvalo zmaže variantu – **len po potvrdení** používateľom                      |
| `new_home_select_variant`          | označí variantu ako vybranú (položka preberie jej cenu, odkaz a fotku)           |
| `new_home_unselect_variant`        | zruší výber varianty (cena, odkaz a fotka položky sa vymažú)                     |
| `new_home_save_variant_comparison` | uloží (prepíše) porovnanie variantov v Markdowne                                 |
| `new_home_set_photo`               | nastaví fotku položky alebo varianty z priameho odkazu na obrázok                |

**Viac miestností a desatinné množstvá:** jedna položka (jedna cena za jednotku) môže byť rozdelená do viacerých miestností, každá s vlastným množstvom – napr. dlažba 24,90 €/m²: Kúpeľňa hore 12,5 m² + Kúpeľňa dole 8 m² + Celý dom 2 m² (rezerva). Cena sa počíta za každú miestnosť zvlášť (zaokrúhlená na centy) a sčíta. `new_home_set_item_rooms` vždy nahrádza celý zoznam, takže pri pridaní miestnosti Claude najprv načíta aktuálne rozdelenie.

**Mazanie** položiek a variantov je nevratné a vyžaduje presné ID; Claude ho urobí až po tvojom výslovnom potvrdení. Mazať môžu len účty s oprávnením na mazanie (admin).

Prompt `compare_item_variants` (argument: názov alebo id položky) prevedie Clauda celým porovnaním – načíta varianty, porovná ich (aj cez odkazy do obchodov), napíše odporúčanie po slovensky a uloží ho do aplikácie.

Fotky sa pridávajú **odkazom na obrázok** (`photo_url` pri vytváraní položky/varianty alebo `new_home_set_photo`) – musí to byť priamy odkaz na obrázok (jpg/png/webp, napr. hlavný obrázok produktu z e-shopu), nie odkaz na stránku produktu. Aplikácia si obrázok stiahne sama.
Ak má položka už vybranú variantu, fotka položky sa berie z nej – vtedy treba fotku nastaviť na variante. Hlasovanie a výber varianty prebiehajú v aplikácii.

## Príklady

- „Čo nám ešte chýba kúpiť do kúpeľne?“
- „Pridaj do obývačky položku Konferenčný stolík, 2 kusy po 89 €, priorita vysoká.“
- „K sedačke pridaj variantu IKEA KIVIK za 799 € s odkazom https://www.ikea.com/sk/…“
- „Porovnaj varianty sedačky a ulož odporúčanie.“ (alebo `/mcp__new-home__compare_item_variants Sedačka`)
- „Koľko ešte minieme za všetko plánované v Celom dome?“
- „Daj dlažbu do kúpeľne hore 12,5 m² a dole 8 m², cena 24,90 € za m².“
- „Pridaj ešte 2 m² dlažby do Celého domu ako rezervu.“
- „Zmeň cenu varianty KIVIK na 749 €.“ / „Vyberte pre sedačku KIVIK.“
- „Zmaž položku Rohožky ku dverám.“
- „Pridaj variantu z tohto odkazu do e-shopu a zober z neho aj fotku.“ (Claude si zo stránky vytiahne obrázok)

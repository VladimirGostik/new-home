import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";

export function registerPrompts(server: McpServer): void {
    server.registerPrompt(
        "compare_item_variants",
        {
            title: "Porovnaj varianty položky",
            description: "Compare all variants of a shopping-list item and save a Slovak Markdown comparison with a recommendation into the app.",
            argsSchema: {
                item: z.string().describe("Item name or UUID, e.g. 'Sedačka do obývačky'."),
            },
        },
        ({ item }) => ({
            messages: [
                {
                    role: "user",
                    content: {
                        type: "text",
                        text: `Porovnaj varianty položky „${item}" v aplikácii Náš nový dom a ulož porovnanie.

Postup:
1. Zavolaj \`new_home_get_item\` s item="${item}". Ak názov nie je jednoznačný, použi \`new_home_list_items\` (search) a opýtaj sa ma, ktorú položku myslím.
2. Ak má položka menej ako 2 varianty, zastav sa a povedz mi to (porovnanie sa nedá uložiť). Ak už existuje porovnanie a nie je zastarané (is_stale=false), opýtaj sa, či ho mám prepísať.
3. Porovnaj VŠETKY varianty: cena za kus aj spolu za množstvo položky, pomer cena/výkon, kľúčové parametre (rozmery, materiál, záruka, dodanie, dostupnosť), výhody a nevýhody. Ak majú varianty odkazy a máš webové nástroje, otvor ich a over parametre a aktuálne ceny; neoverené údaje jasne označ.
4. Napíš porovnanie po slovensky v Markdowne: krátky úvod, tabuľka (varianta | cena/ks | spolu | hlavné parametre), Výhody / Nevýhody pre každú variantu, aktuálne hlasy rodiny a na konci sekcia „## Odporúčanie" s JEDNOU odporúčanou variantou a zdôvodnením (plus alternatívu, kedy dáva zmysel).
5. Ulož ho cez \`new_home_save_variant_comparison\` a stručne mi zhrň odporúčanie.`,
                    },
                },
            ],
        }),
    );
}

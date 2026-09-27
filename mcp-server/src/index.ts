#!/usr/bin/env node
/**
 * MCP server for the "Náš nový dom" family shopping-list app.
 * Transport: stdio. Config via env: NEW_HOME_API_URL, NEW_HOME_EMAIL, NEW_HOME_PASSWORD.
 * All logging goes to stderr (stdout is the MCP channel); credentials are never logged.
 */
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { SERVER_NAME, SERVER_VERSION } from "./constants.js";
import { registerPrompts } from "./prompts.js";
import { NewHomeApiClient } from "./services/api-client.js";
import { registerItemTools } from "./tools/items.js";
import { registerPhotoTools } from "./tools/photos.js";
import { registerRoomTools } from "./tools/rooms.js";
import { registerVariantTools } from "./tools/variants.js";

async function main(): Promise<void> {
    const client = new NewHomeApiClient();
    const server = new McpServer(
        { name: SERVER_NAME, version: SERVER_VERSION },
        {
            instructions:
                "Tools for the family shopping list of a new house (Room → Item → Variant). The family speaks Slovak; answer in Slovak. " +
                "Items without a room belong to 'Celý dom'. An item can be split across several rooms with DECIMAL quantities (e.g. tiles: Kúpeľňa hore 12.5 m² + Kúpeľňa dole 8 m²): " +
                "create with rooms[] or change with new_home_set_item_rooms (full replacement — read current rows first). Prices are EUR per unit; totals are per-room line totals summed. " +
                "Destructive tools (new_home_delete_item, new_home_delete_variant) need an explicit user confirmation naming the exact item/variant first; select/unselect a variant only when the user asks. " +
                "To compare variants, use the compare_item_variants workflow: " +
                "new_home_get_item → analyse all variants → new_home_save_variant_comparison. " +
                "Photos are added by DIRECT image URL (photo_url, e.g. the shop's og:image), never by the product page URL; use new_home_set_photo for existing items/variants. " +
                "If an item already has a selected variant, its photo comes from that variant — set the photo on the variant.",
        },
    );

    registerRoomTools(server, client);
    registerItemTools(server, client);
    registerVariantTools(server, client);
    registerPhotoTools(server, client);
    registerPrompts(server);

    await server.connect(new StdioServerTransport());

    const hasCredentials = Boolean(process.env.NEW_HOME_EMAIL && process.env.NEW_HOME_PASSWORD);
    console.error(
        `${SERVER_NAME} ${SERVER_VERSION} running on stdio → ${client.baseUrl}${hasCredentials ? "" : " (WARNING: NEW_HOME_EMAIL / NEW_HOME_PASSWORD not set)"}`,
    );
}

main().catch((error: unknown) => {
    console.error(`${SERVER_NAME} failed to start:`, error instanceof Error ? error.message : error);
    process.exit(1);
});

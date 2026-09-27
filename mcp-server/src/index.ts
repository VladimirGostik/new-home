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
import { registerRoomTools } from "./tools/rooms.js";
import { registerVariantTools } from "./tools/variants.js";

async function main(): Promise<void> {
    const client = new NewHomeApiClient();
    const server = new McpServer(
        { name: SERVER_NAME, version: SERVER_VERSION },
        {
            instructions:
                "Tools for the family shopping list of a new house (Room → Item → Variant). The family speaks Slovak; answer in Slovak. " +
                "Items without a room belong to 'Celý dom'. Prices are EUR. To compare variants, use the compare_item_variants workflow: " +
                "new_home_get_item → analyse all variants → new_home_save_variant_comparison.",
        },
    );

    registerRoomTools(server, client);
    registerItemTools(server, client);
    registerVariantTools(server, client);
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

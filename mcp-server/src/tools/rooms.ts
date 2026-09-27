import type { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import { WHOLE_HOUSE_FILTER, WHOLE_HOUSE_LABEL } from "../constants.js";
import type { NewHomeApiClient } from "../services/api-client.js";
import { roomsMarkdown, toolError, toolResult } from "../services/format.js";
import { listRooms } from "../services/resolvers.js";
import { responseFormatSchema } from "./schemas.js";

export function registerRoomTools(server: McpServer, client: NewHomeApiClient): void {
    server.registerTool(
        "new_home_list_rooms",
        {
            title: "List rooms",
            description: `List all rooms of the house (id + name) in the "Náš nový dom" shopping-list app.

Items can belong to a room or to no room at all — the app shows those as "${WHOLE_HOUSE_LABEL}" (whole house); filter value "${WHOLE_HOUSE_FILTER}".
Other tools accept room names directly (case/diacritics-insensitive), so you only need this to show the user the options or to disambiguate.

Returns: { rooms: [{ id, name }] }`,
            inputSchema: z.object({ response_format: responseFormatSchema }).strict(),
            annotations: { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
        },
        async ({ response_format }) => {
            try {
                const rooms = await listRooms(client);
                return toolResult(response_format, { rooms }, () => roomsMarkdown(rooms));
            } catch (error) {
                return toolError(error);
            }
        },
    );
}

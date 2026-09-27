export const SERVER_NAME = "new-home-mcp-server";
export const SERVER_VERSION = "1.2.0";

/** Default base URL of the local docker app. */
export const DEFAULT_API_URL = "http://localhost:8003";

/** Max characters of a single tool response before it gets truncated. */
export const CHARACTER_LIMIT = 25_000;

/** Per-request timeout for API calls. */
export const REQUEST_TIMEOUT_MS = 20_000;

/** Mirrors App\Enums\ItemStatus. */
export const ITEM_STATUSES = ["planned", "bought"] as const;
/** Mirrors App\Enums\ItemPriority. */
export const ITEM_PRIORITIES = ["low", "medium", "high"] as const;

export const STATUS_LABELS: Record<string, string> = {
    planned: "Plánované",
    bought: "Kúpené",
};

export const PRIORITY_LABELS: Record<string, string> = {
    low: "Nízka",
    medium: "Stredná",
    high: "Vysoká",
};

/** Label used by the app for items without a room (room_id = null). */
export const WHOLE_HOUSE_LABEL = "Celý dom";
/** Filter value the API uses for items without a room. */
export const WHOLE_HOUSE_FILTER = "house";

export const ITEM_SORTS = [
    "name",
    "-name",
    "created_at",
    "-created_at",
    "priority",
    "-priority",
    "status",
    "-status",
] as const;

export enum ResponseFormat {
    MARKDOWN = "markdown",
    JSON = "json",
}

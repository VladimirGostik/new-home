import { DEFAULT_API_URL, REQUEST_TIMEOUT_MS } from "../constants.js";
import type { AuthToken } from "../types.js";

type QueryValue = string | number | undefined | null;
type HttpMethod = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";

interface RequestOptions {
    query?: Record<string, QueryValue>;
    body?: unknown;
}

/** Error whose message is safe and actionable to show to the model/user. */
export class ApiError extends Error {
    constructor(
        message: string,
        readonly status?: number,
    ) {
        super(message);
        this.name = "ApiError";
    }
}

interface LaravelErrorBody {
    message?: string;
    errors?: Record<string, string[]>;
}

function readConfig(): { baseUrl: string; email: string | undefined; password: string | undefined } {
    const rawUrl = process.env.NEW_HOME_API_URL?.trim() || DEFAULT_API_URL;
    return {
        baseUrl: rawUrl.replace(/\/+$/, ""),
        email: process.env.NEW_HOME_EMAIL?.trim() || undefined,
        password: process.env.NEW_HOME_PASSWORD || undefined,
    };
}

function formatValidationErrors(body: LaravelErrorBody): string {
    const fields = Object.entries(body.errors ?? {});
    if (fields.length === 0) {
        return body.message ?? "Validation failed.";
    }
    return fields.map(([field, messages]) => `- ${field}: ${messages.join(" ")}`).join("\n");
}

async function parseBody(response: Response): Promise<unknown> {
    const text = await response.text();
    if (text === "") {
        return null;
    }
    try {
        return JSON.parse(text) as unknown;
    } catch {
        return text;
    }
}

function asErrorBody(body: unknown): LaravelErrorBody {
    return typeof body === "object" && body !== null ? (body as LaravelErrorBody) : {};
}

/**
 * Thin client for the "Náš nový dom" JSON API (Laravel + Sanctum).
 * Logs in lazily once per process, caches the Bearer token and re-logs in once on 401.
 */
export class NewHomeApiClient {
    private token: string | null = null;
    private loginInFlight: Promise<string> | null = null;
    /** Remembered credential rejection — avoids burning the 5/min login rate limit on every tool call. */
    private credentialError: ApiError | null = null;
    private readonly config = readConfig();

    get baseUrl(): string {
        return this.config.baseUrl;
    }

    async get<T>(path: string, query?: Record<string, QueryValue>): Promise<T> {
        return this.request<T>("GET", path, { query });
    }

    async post<T>(path: string, body: unknown): Promise<T> {
        return this.request<T>("POST", path, { body });
    }

    async put<T>(path: string, body: unknown): Promise<T> {
        return this.request<T>("PUT", path, { body });
    }

    async patch<T>(path: string, body: unknown): Promise<T> {
        return this.request<T>("PATCH", path, { body });
    }

    /** DELETE returning 204 No Content (an empty body parses to null). */
    async delete(path: string): Promise<void> {
        await this.request<null>("DELETE", path, {});
    }

    /** DELETE that returns a JSON body (e.g. 200 ItemDetailData). */
    async deleteWithBody<T>(path: string): Promise<T> {
        return this.request<T>("DELETE", path, {});
    }

    private async request<T>(method: HttpMethod, path: string, options: RequestOptions): Promise<T> {
        let token = await this.getToken();
        let response = await this.send(method, path, options, token);

        if (response.status === 401) {
            // Token revoked or expired — log in again exactly once.
            this.token = null;
            token = await this.getToken();
            response = await this.send(method, path, options, token);
        }

        const body = await parseBody(response);
        if (!response.ok) {
            throw this.toApiError(response, body, path);
        }
        return body as T;
    }

    private async getToken(): Promise<string> {
        if (this.token) {
            return this.token;
        }
        if (!this.loginInFlight) {
            this.loginInFlight = this.login().finally(() => {
                this.loginInFlight = null;
            });
        }
        this.token = await this.loginInFlight;
        return this.token;
    }

    private async login(): Promise<string> {
        if (this.credentialError) {
            throw this.credentialError;
        }
        const { email, password } = this.config;
        if (!email || !password) {
            throw new ApiError(
                "Missing credentials: set NEW_HOME_EMAIL and NEW_HOME_PASSWORD in the environment of the MCP server " +
                    "(e.g. export them in your shell before starting Claude Code; .mcp.json expands ${NEW_HOME_EMAIL} / ${NEW_HOME_PASSWORD}).",
            );
        }

        const response = await this.send("POST", "/auth/login", { body: { email, password } }, null);
        const body = await parseBody(response);

        if (response.ok) {
            const token = (body as Partial<AuthToken> | null)?.token;
            if (typeof token !== "string" || token === "") {
                throw new ApiError("Login succeeded but the API response did not contain a token. Is NEW_HOME_API_URL pointing at the right app?");
            }
            return token;
        }
        if (response.status === 422 || response.status === 401) {
            throw (this.credentialError = new ApiError(
                "Login failed: the app rejected NEW_HOME_EMAIL / NEW_HOME_PASSWORD. Check the credentials (same as the web login) and restart the MCP server.",
                response.status,
            ));
        }
        if (response.status === 429) {
            throw new ApiError(
                `Login is rate limited (max 5 attempts per minute). ${this.retryAfterHint(response)}Wait and try again; do not retry in a loop.`,
                429,
            );
        }
        throw this.toApiError(response, body, "/auth/login");
    }

    private async send(method: HttpMethod, path: string, options: RequestOptions, token: string | null): Promise<Response> {
        const url = new URL(`${this.config.baseUrl}/api${path}`);
        for (const [key, value] of Object.entries(options.query ?? {})) {
            if (value !== undefined && value !== null && value !== "") {
                url.searchParams.set(key, String(value));
            }
        }

        const headers: Record<string, string> = { Accept: "application/json" };
        if (options.body !== undefined) {
            headers["Content-Type"] = "application/json";
        }
        if (token) {
            headers.Authorization = `Bearer ${token}`;
        }

        try {
            return await fetch(url, {
                method,
                headers,
                body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
                signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS),
            });
        } catch (error) {
            throw this.toNetworkError(error);
        }
    }

    private toNetworkError(error: unknown): ApiError {
        if (error instanceof Error && (error.name === "TimeoutError" || error.name === "AbortError")) {
            return new ApiError(`The app at ${this.config.baseUrl} did not respond within ${REQUEST_TIMEOUT_MS / 1000}s. Is it overloaded or stuck? Try again shortly.`);
        }
        const cause = error instanceof Error ? (error.cause as { code?: string } | undefined) : undefined;
        const code = cause?.code ?? "";
        if (["ECONNREFUSED", "ECONNRESET", "ENOTFOUND", "EAI_AGAIN", "EHOSTUNREACH"].includes(code)) {
            const isLocal = /\/\/(localhost|127\.0\.0\.1)(:|\/|$)/.test(this.config.baseUrl);
            return new ApiError(
                isLocal
                    ? `Cannot connect to the app at ${this.config.baseUrl} (${code}). Is docker running on :8003? Start it with \`docker compose up -d\` in the new-home repo, or set NEW_HOME_API_URL.`
                    : `Cannot connect to the app at ${this.config.baseUrl} (${code}). Check the internet connection and the app URL (NEW_HOME_API_URL / "Adresa aplikácie" in the extension settings), then try again.`,
            );
        }
        const detail = error instanceof Error ? error.message : String(error);
        return new ApiError(`Network error while calling ${this.config.baseUrl}: ${detail}`);
    }

    private retryAfterHint(response: Response): string {
        const retryAfter = response.headers.get("retry-after");
        return retryAfter ? `Retry after ${retryAfter}s. ` : "";
    }

    private toApiError(response: Response, rawBody: unknown, path: string): ApiError {
        const body = asErrorBody(rawBody);
        const status = response.status;
        switch (status) {
            case 401:
                return new ApiError("Not authenticated even after re-login. Check NEW_HOME_EMAIL / NEW_HOME_PASSWORD and restart the MCP server.", status);
            case 403:
                return new ApiError(`Forbidden: this account is not allowed to do that (${path}). ${body.message ?? ""}`.trim(), status);
            case 404:
                return new ApiError(
                    `Not found (${path}). If you used an item/room id, it may be wrong or deleted — use new_home_list_items or new_home_list_rooms to find the right one. ` +
                        "If every call returns 404, the app's JSON API may not be deployed yet at NEW_HOME_API_URL.",
                    status,
                );
            case 422:
                return new ApiError(`Validation failed (${body.message ?? "invalid input"}):\n${formatValidationErrors(body)}\nFix the listed fields and try again.`, status);
            case 429:
                return new ApiError(
                    `Too many requests (photo downloads are limited to 30 per minute). ${this.retryAfterHint(response)}Wait a moment and retry later; do not retry in a loop.`,
                    status,
                );
            default:
                if (status >= 500) {
                    return new ApiError(`The app returned a server error (HTTP ${status}) for ${path}. Check the Laravel logs (storage/logs) or try again.`, status);
                }
                return new ApiError(`Unexpected HTTP ${status} for ${path}: ${body.message ?? response.statusText}`, status);
        }
    }
}

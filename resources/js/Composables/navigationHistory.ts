import { router } from '@inertiajs/vue3';

// Tracks the previous and current in-app URL from Inertia navigations, so back
// links can rely on real navigation history instead of `document.referrer`
// (stale after a full page reload — e.g. after a deploy — or a session-old page).
let previousUrl: string | null = null;
let currentUrl: string | null = null;

export function initNavigationHistory(): void {
    currentUrl = window.location.pathname + window.location.search;

    router.on('navigate', (event: Event) => {
        const nextUrl = (event as unknown as { detail: { page: { url: string } } }).detail.page.url;
        if (nextUrl === currentUrl) return;
        previousUrl = currentUrl;
        currentUrl = nextUrl;
    });
}

export function getPreviousUrl(): string | null {
    return previousUrl;
}

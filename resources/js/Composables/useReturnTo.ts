import { getPreviousUrl } from './navigationHistory';

const FORM_PAGE_PATTERNS = [
    /^\/items\/[^/]+\/edit(?:\?|$)/,
    /^\/items\/create(?:\?|$)/,
    /^\/rooms\/[^/]+\/edit(?:\?|$)/,
    /^\/rooms\/create(?:\?|$)/,
];

function isFormPage(url: string): boolean {
    return FORM_PAGE_PATTERNS.some((pattern) => pattern.test(url));
}

export function useReturnTo(fallback = '/items'): string {
    const query = new URLSearchParams(window.location.search);
    const explicit = query.get('return');
    if (explicit?.startsWith('/') && !explicit.startsWith('//')) return explicit;

    const previous = getPreviousUrl();
    const current = window.location.pathname + window.location.search;
    if (previous && previous !== current && !isFormPage(previous)) {
        return previous;
    }

    return fallback;
}

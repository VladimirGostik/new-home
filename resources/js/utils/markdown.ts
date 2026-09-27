import { Marked } from 'marked';
import DOMPurify from 'dompurify';

const marked = new Marked({ async: false, gfm: true, breaks: true });

const ALLOWED_TAGS = [
    'p',
    'br',
    'strong',
    'em',
    'del',
    'ul',
    'ol',
    'li',
    'h1',
    'h2',
    'h3',
    'h4',
    'blockquote',
    'code',
    'pre',
    'hr',
    'a',
    'table',
    'thead',
    'tbody',
    'tr',
    'th',
    'td',
];

const ALLOWED_ATTR = ['href', 'title', 'target', 'rel'];

DOMPurify.addHook('afterSanitizeAttributes', (node) => {
    if (node.tagName === 'A') {
        node.setAttribute('target', '_blank');
        node.setAttribute('rel', 'noopener noreferrer');
    }
});

export function renderMarkdown(source: string): string {
    const html = marked.parse(source, { async: false }) as string;
    return DOMPurify.sanitize(html, { ALLOWED_TAGS, ALLOWED_ATTR });
}

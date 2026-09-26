/// <reference types="vite/client" />

import '../css/app.css';

import { createApp, h, type DefineComponent, type Plugin } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createI18n } from 'vue-i18n';

import enApp from '../lang/en/app.json';
import skApp from '../lang/sk/app.json';

const appName = (import.meta.env.VITE_APP_NAME as string | undefined) ?? 'App';

// Flat keys (`t('items')`) plus the same set under `app.` for components that call `t('app.items')`.
const messages = {
    en: { ...enApp, app: enApp },
    sk: { ...skApp, app: skApp },
};

/** Slovak plural forms: "0 | 1 | 2–4 | 5+" (e.g. žiadne položky | 1 položka | 3 položky | 7 položiek). */
function slovakPlural(choice: number, choicesLength: number): number {
    const n = Math.abs(choice);
    if (choicesLength !== 4) return n === 1 ? 0 : 1;
    if (n === 0) return 0;
    if (n === 1) return 1;
    return n >= 2 && n <= 4 ? 2 : 3;
}

void createInertiaApp({
    title: (title: string | null) =>
        title ? `${title} - ${appName}` : appName,
    resolve: (name: string) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup: ({
        el,
        App,
        props,
        plugin,
    }: {
        el: HTMLElement;
        App: DefineComponent;
        props: Record<string, unknown>;
        plugin: Plugin;
    }) => {
        const initialPage = props.initialPage as { props: Record<string, unknown> } | undefined;
        const locale = (initialPage?.props?.locale as string | undefined) ?? 'en';

        const i18n = createI18n({
            legacy: false,
            locale,
            fallbackLocale: 'en',
            messages,
            pluralRules: { sk: slovakPlural },
        });

        router.on('navigate', (event: Event) => {
            const page = (event as unknown as { detail: { page: { props: Record<string, unknown> } } }).detail.page;
            const newLocale = page?.props?.locale as string | undefined;
            if (newLocale) {
                (i18n.global.locale as unknown as { value: string }).value = newLocale;
            }
        });

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .mount(el);
    },
    progress: { color: '#56642a' },
});

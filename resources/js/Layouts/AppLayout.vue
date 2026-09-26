<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import {
    HomeIcon,
    ClipboardDocumentListIcon,
    UsersIcon,
    ShieldCheckIcon,
    UserCircleIcon,
    UserIcon,
    Cog6ToothIcon,
    ArrowRightOnRectangleIcon,
    PhotoIcon,
    EnvelopeIcon,
    HomeModernIcon,
    ListBulletIcon,
    Squares2X2Icon,
    PlusIcon,
} from '@heroicons/vue/24/outline';
import type { ToastPayload } from '@/Composables/useToast';
import BrandMark from '@/Components/BrandMark.vue';

type NavigationItem = App.Data.NavigationItemData;

const { t } = useI18n();
const page = usePage();

const appName = (import.meta.env.VITE_APP_NAME as string | undefined) ?? 'App';

const auth = computed(() => page.props.auth);
const navigation = computed<NavigationItem[]>(() => page.props.navigation ?? []);
const can = computed(() => (page.props.can ?? {}) as Record<string, boolean>);

const mainNav = computed(() => navigation.value.filter((item) => item.children.length === 0));
const settingsNav = computed(() => navigation.value.find((item) => item.key === 'group:settings')?.children ?? []);

/** Bottom-bar icons follow the design (grid for rooms), independent of the sidebar icon names. */
const TAB_ICONS: Record<string, object> = {
    dashboard: HomeIcon,
    'items.index': ListBulletIcon,
    'items.mine': UserIcon,
    'rooms.index': Squares2X2Icon,
};

const TAB_LABELS: Record<string, string> = {
    dashboard: 'dashboard',
    'items.index': 'items',
    'items.mine': 'my_items_short',
    'rooms.index': 'rooms',
};

const ICONS: Record<string, object> = {
    HomeIcon,
    UsersIcon,
    UserIcon,
    ShieldCheckIcon,
    ClipboardDocumentListIcon,
    PhotoIcon,
    EnvelopeIcon,
    UserCircleIcon,
    Cog6ToothIcon,
    HomeModernIcon,
    ListBulletIcon,
    Squares2X2Icon,
};

function resolveIcon(name: string): object {
    return ICONS[name] ?? HomeIcon;
}

function translateLabel(label: string): string {
    return label.startsWith('app.') ? t(label.slice(4)) : t(label);
}

const initials = computed(() => {
    const name: string = auth.value.user?.name ?? '';
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
});

/**
 * Mobile "+" button per page. The layout is persistent (set in app.ts), so it derives the
 * target from the current page instead of a prop. Pages not listed here show no button.
 */
const fab = computed<string | null>(() => {
    if (can.value.createItems !== true) return null;
    switch (page.component) {
        case 'Dashboard':
        case 'Items/Index':
        case 'Items/Mine':
        case 'Rooms/Index':
            return '/items/create';
        case 'Rooms/Show': {
            const room = page.props.room as { id: string } | undefined;
            if (!room) return '/items/create';
            return `/items/create?room=${room.id}&return=${encodeURIComponent(`/rooms/${room.id}`)}`;
        }
        default:
            return null;
    }
});

interface ToastMessage {
    id: number;
    message: string;
    type: 'success' | 'error' | 'info';
}

const toasts = reactive<ToastMessage[]>([]);
let toastCounter = 0;

function addToast(message: string, type: ToastMessage['type']) {
    if (!message) return;
    const id = ++toastCounter;
    toasts.push({ id, message, type });
    setTimeout(() => {
        const index = toasts.findIndex((toast) => toast.id === id);
        if (index !== -1) toasts.splice(index, 1);
    }, 3500);
}

function handleToastEvent(event: Event) {
    const { message, type } = (event as CustomEvent<ToastPayload>).detail;
    addToast(message, type);
}

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) addToast(flash.success, 'success');
        if (flash?.error) addToast(flash.error, 'error');
        if (flash?.info) addToast(flash.info, 'info');
    },
    { immediate: true, deep: true },
);

onMounted(() => window.addEventListener('app-toast', handleToastEvent));
onUnmounted(() => window.removeEventListener('app-toast', handleToastEvent));

function logout() {
    router.post('/logout');
}

function isActive(href: string): boolean {
    const path = page.url.split('?')[0];
    if (href === '/') return path === '/' || path === '';
    return path === href || path.startsWith(`${href}/`);
}

function toastAlertClass(type: ToastMessage['type']): string {
    if (type === 'success') return 'alert-success';
    if (type === 'error') return 'alert-error';
    return 'alert-info';
}
</script>

<template>
    <div
        class="min-h-dvh bg-paper text-ink lg:flex"
        data-theme="app-theme"
    >
        <!-- Desktop sidebar -->
        <aside class="hidden lg:flex lg:w-62 lg:shrink-0 lg:flex-col lg:gap-7 lg:bg-ink lg:px-4 lg:py-6 lg:text-paper sticky top-0 h-dvh">
            <Link
                href="/"
                class="flex items-center gap-2.5 px-2"
            >
                <BrandMark class="size-8.5" />
                <span class="font-display text-[19px] font-semibold">{{ appName }}</span>
            </Link>

            <nav
                :aria-label="t('main_navigation')"
                class="flex flex-col gap-1"
            >
                <Link
                    v-for="item in mainNav"
                    :key="item.key"
                    :href="item.href"
                    :aria-current="isActive(item.href) ? 'page' : undefined"
                    class="flex h-11 items-center gap-3 rounded-xl px-3 text-[15px] transition-colors"
                    :class="isActive(item.href) ? 'bg-ink-soft font-semibold text-paper' : 'text-[#cfc8b8] hover:bg-ink-soft hover:text-paper'"
                >
                    <span
                        class="h-4.5 w-1 rounded-full"
                        :class="isActive(item.href) ? 'bg-oak' : 'bg-transparent'"
                    />
                    {{ translateLabel(item.label) }}
                </Link>
            </nav>

            <div class="mt-auto flex flex-col gap-1 border-t border-[#33332d] pt-4">
                <span
                    v-if="settingsNav.length"
                    class="px-3 pb-1.5 text-xs uppercase tracking-[0.08em] text-[#8f897b]"
                >{{ t('settings') }}</span>
                <Link
                    v-for="child in settingsNav"
                    :key="child.key"
                    :href="child.href"
                    class="flex h-9.5 items-center gap-2.5 rounded-lg px-3 text-sm transition-colors"
                    :class="isActive(child.href) ? 'bg-ink-soft text-paper' : 'text-[#cfc8b8] hover:text-paper'"
                >
                    <component
                        :is="resolveIcon(child.icon)"
                        class="size-4"
                    />
                    {{ translateLabel(child.label) }}
                </Link>

                <div
                    v-if="auth.user"
                    class="mt-3 flex items-center gap-3 rounded-xl bg-ink-soft px-3 py-2.5"
                >
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-olive text-sm font-semibold text-white">{{ initials }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium">{{ auth.user.name }}</span>
                        <span class="block truncate text-xs text-[#8f897b]">{{ auth.user.email }}</span>
                    </span>
                    <button
                        type="button"
                        class="btn btn-ghost btn-sm btn-square text-[#cfc8b8] hover:bg-ink hover:text-paper"
                        :aria-label="t('logout')"
                        @click="logout"
                    >
                        <ArrowRightOnRectangleIcon class="size-5" />
                    </button>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Mobile top bar -->
            <header class="sticky top-0 z-20 flex items-center justify-between bg-paper/95 px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-2 backdrop-blur lg:hidden">
                <Link
                    href="/"
                    class="flex items-center gap-2.5"
                >
                    <BrandMark class="size-8" />
                    <span class="font-display text-[19px] font-semibold tracking-tight">{{ appName }}</span>
                </Link>

                <div class="dropdown dropdown-end">
                    <button
                        type="button"
                        class="flex size-11 items-center justify-center rounded-full border border-line bg-white text-sm font-semibold"
                        :aria-label="t('account_menu')"
                    >
                        {{ initials }}
                    </button>
                    <ul
                        tabindex="0"
                        class="dropdown-content menu z-30 mt-2 w-60 rounded-box border border-line bg-white p-2 shadow-lg"
                    >
                        <li
                            v-if="auth.user"
                            class="menu-title"
                        >
                            <span class="truncate">{{ auth.user.name }}</span>
                        </li>
                        <li
                            v-for="child in settingsNav"
                            :key="child.key"
                        >
                            <Link
                                :href="child.href"
                                class="min-h-11"
                            >
                                <component
                                    :is="resolveIcon(child.icon)"
                                    class="size-5"
                                />
                                {{ translateLabel(child.label) }}
                            </Link>
                        </li>
                        <li>
                            <button
                                type="button"
                                class="min-h-11 text-error"
                                @click="logout"
                            >
                                <ArrowRightOnRectangleIcon class="size-5" />
                                {{ t('logout') }}
                            </button>
                        </li>
                    </ul>
                </div>
            </header>

            <main class="mx-auto w-full max-w-5xl flex-1 px-4 pt-3 pb-32 lg:px-10 lg:pt-9 lg:pb-12">
                <slot />
            </main>
        </div>

        <!-- Mobile: floating add button -->
        <Link
            v-if="fab"
            :href="fab"
            :aria-label="t('add_item')"
            class="fixed right-5 bottom-[calc(5.75rem+env(safe-area-inset-bottom))] z-20 flex size-14 items-center justify-center rounded-[18px] bg-olive text-white shadow-[0_8px_20px_rgba(62,74,27,0.28)] transition-transform active:scale-95 lg:hidden"
        >
            <PlusIcon class="size-6 stroke-2" />
        </Link>

        <!-- Mobile: bottom tab bar -->
        <nav
            :aria-label="t('main_navigation')"
            class="pb-safe fixed inset-x-0 bottom-0 z-20 grid grid-cols-4 border-t border-line bg-white px-2 pt-1 lg:hidden"
        >
            <Link
                v-for="item in mainNav"
                :key="item.key"
                :href="item.href"
                :aria-current="isActive(item.href) ? 'page' : undefined"
                class="flex min-h-15 flex-col items-center justify-center gap-1 text-xs"
                :class="isActive(item.href) ? 'font-semibold text-olive' : 'text-muted'"
            >
                <component
                    :is="TAB_ICONS[item.key] ?? resolveIcon(item.icon)"
                    class="size-5.5"
                />
                {{ t(TAB_LABELS[item.key] ?? item.label) }}
            </Link>
        </nav>
    </div>

    <div class="toast toast-top toast-center z-50 w-[calc(100%-2rem)] max-w-md lg:toast-bottom lg:toast-end">
        <div
            v-for="toast in toasts"
            :key="toast.id"
            role="status"
            class="alert shadow-lg"
            :class="toastAlertClass(toast.type)"
        >
            <span>{{ toast.message }}</span>
        </div>
    </div>
</template>

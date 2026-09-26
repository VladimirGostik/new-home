<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ArrowRightOnRectangleIcon } from '@heroicons/vue/24/outline';
import { useDropdown } from '@/Composables/useDropdown';
import { resolveIcon, translateLabel } from '@/utils/navigation';

defineProps<{
    initials: string;
    userName: string | null;
    items: App.Data.NavigationItemData[];
}>();

const { t } = useI18n();

const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLButtonElement | null>(null);
const { isOpen, close, toggle } = useDropdown(root);

function handleEscapeCapture(event: KeyboardEvent) {
    if (event.key === 'Escape' && isOpen.value) {
        trigger.value?.focus();
    }
}

onMounted(() => document.addEventListener('keydown', handleEscapeCapture, true));
onUnmounted(() => document.removeEventListener('keydown', handleEscapeCapture, true));

function logout() {
    close();
    router.post('/logout');
}
</script>

<template>
    <div
        ref="root"
        class="dropdown dropdown-end"
        :class="isOpen ? 'dropdown-open' : 'dropdown-close'"
    >
        <button
            ref="trigger"
            type="button"
            class="flex size-11 items-center justify-center rounded-full border border-line bg-white text-sm font-semibold"
            aria-haspopup="true"
            :aria-expanded="isOpen"
            aria-controls="account-menu"
            :aria-label="t('account_menu')"
            @click="toggle"
        >
            {{ initials }}
        </button>
        <ul
            id="account-menu"
            class="dropdown-content menu mt-2 w-60 rounded-box border border-line bg-white p-2 shadow-lg"
        >
            <li
                v-if="userName"
                class="menu-title"
            >
                <span class="truncate">{{ userName }}</span>
            </li>
            <li
                v-for="child in items"
                :key="child.key"
            >
                <Link
                    :href="child.href"
                    class="min-h-11"
                    @click="close"
                >
                    <component
                        :is="resolveIcon(child.icon)"
                        class="size-5"
                    />
                    {{ translateLabel(t, child.label) }}
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
</template>

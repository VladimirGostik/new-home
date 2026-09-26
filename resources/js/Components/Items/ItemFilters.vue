<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/vue/24/outline';

interface Option {
    id: string;
    name: string;
}

export interface ItemFilterOptions {
    rooms: Option[];
    users: Option[];
    statuses: Option[];
    priorities: Option[];
}

const props = defineProps<{
    filters: Record<string, unknown>;
    options: ItemFilterOptions;
}>();

const { t } = useI18n();

type FilterKey = 'search' | 'room' | 'assignee' | 'status' | 'priority' | 'price';

const active = computed<Record<string, string>>(() => {
    const raw = (props.filters.filter ?? {}) as Record<string, unknown>;
    return Object.fromEntries(Object.entries(raw).map(([key, value]) => [key, String(value ?? '')]));
});

const search = ref(active.value.search ?? '');

function apply(next: Partial<Record<FilterKey, string>>) {
    const filter = { ...active.value, ...next };
    const cleaned = Object.fromEntries(Object.entries(filter).filter(([, value]) => value !== ''));
    const sort = props.filters.sort;

    router.get(
        '/items',
        { ...(Object.keys(cleaned).length ? { filter: cleaned } : {}), ...(sort ? { sort } : {}) },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;
function onSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => apply({ search: search.value.trim() }), 300);
}
onBeforeUnmount(() => clearTimeout(searchTimer));

function onSelect(key: FilterKey, event: Event) {
    apply({ [key]: (event.target as HTMLSelectElement).value });
}

const hasAny = computed(() => Object.values(active.value).some((value) => value !== ''));

function clearAll() {
    search.value = '';
    router.get('/items', {}, { preserveState: true, preserveScroll: true, replace: true });
}

function chipClass(key: FilterKey): string {
    return active.value[key] ? 'border-ink bg-ink text-paper' : 'border-line bg-white text-ink';
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <label class="flex h-12 items-center gap-2.5 rounded-[14px] border border-line bg-white px-3.5 text-muted focus-within:border-olive">
            <MagnifyingGlassIcon class="size-5 shrink-0" />
            <input
                v-model="search"
                type="search"
                enterkeyhint="search"
                :placeholder="t('search_by_name')"
                :aria-label="t('search_by_name')"
                class="min-w-0 flex-1 bg-transparent text-base text-ink outline-none"
                @input="onSearch"
            />
        </label>

        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] lg:mx-0 lg:flex-wrap lg:px-0">
            <select
                :value="active.status ?? ''"
                :aria-label="t('status')"
                class="select h-9 w-auto shrink-0 rounded-full pr-8 text-sm font-medium"
                :class="chipClass('status')"
                @change="onSelect('status', $event)"
            >
                <option value="">{{ t('all_statuses') }}</option>
                <option
                    v-for="option in options.statuses"
                    :key="option.id"
                    :value="option.id"
                >{{ option.name }}</option>
            </select>

            <select
                :value="active.room ?? ''"
                :aria-label="t('room')"
                class="select h-9 w-auto shrink-0 rounded-full pr-8 text-sm font-medium"
                :class="chipClass('room')"
                @change="onSelect('room', $event)"
            >
                <option value="">{{ t('all_rooms') }}</option>
                <option value="house">{{ t('whole_house') }}</option>
                <option
                    v-for="option in options.rooms"
                    :key="option.id"
                    :value="option.id"
                >{{ option.name }}</option>
            </select>

            <select
                :value="active.assignee ?? ''"
                :aria-label="t('assigned_to')"
                class="select h-9 w-auto shrink-0 rounded-full pr-8 text-sm font-medium"
                :class="chipClass('assignee')"
                @change="onSelect('assignee', $event)"
            >
                <option value="">{{ t('all_people') }}</option>
                <option value="me">{{ t('me') }}</option>
                <option value="none">{{ t('unassigned_group') }}</option>
                <option
                    v-for="option in options.users"
                    :key="option.id"
                    :value="option.id"
                >{{ option.name }}</option>
            </select>

            <select
                :value="active.priority ?? ''"
                :aria-label="t('priority')"
                class="select h-9 w-auto shrink-0 rounded-full pr-8 text-sm font-medium"
                :class="chipClass('priority')"
                @change="onSelect('priority', $event)"
            >
                <option value="">{{ t('all_priorities') }}</option>
                <option
                    v-for="option in options.priorities"
                    :key="option.id"
                    :value="option.id"
                >{{ option.name }}</option>
            </select>

            <button
                v-if="active.price === 'none'"
                type="button"
                class="btn h-9 min-h-0 shrink-0 rounded-full border-ink bg-ink px-3 text-sm font-medium text-paper"
                @click="apply({ price: '' })"
            >
                {{ t('no_price') }}
                <XMarkIcon class="size-4" />
            </button>

            <button
                v-if="hasAny"
                type="button"
                class="btn btn-ghost h-9 min-h-0 shrink-0 rounded-full px-3 text-sm text-muted"
                @click="clearAll"
            >
                {{ t('clear_filters') }}
            </button>
        </div>
    </div>
</template>

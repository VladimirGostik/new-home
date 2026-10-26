<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    filters: App.Data.ExpenseIndexFilterData;
    types: { id: string; name: string }[];
}>();

const emit = defineEmits<{
    change: [next: { search?: string; type?: string }];
}>();

const { t } = useI18n();

const search = ref(props.filters.search ?? '');
const hasAny = computed(() => !!props.filters.search || !!props.filters.type);

let searchTimer: ReturnType<typeof setTimeout> | undefined;
function onSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => emit('change', { search: search.value.trim() }), 300);
}
onBeforeUnmount(() => clearTimeout(searchTimer));

function onType(event: Event) {
    emit('change', { type: (event.target as HTMLSelectElement).value });
}

function clearAll() {
    clearTimeout(searchTimer);
    search.value = '';
    emit('change', { search: '', type: '' });
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <label
            class="flex h-12 items-center gap-2.5 rounded-[14px] border border-line bg-white px-3.5 text-muted focus-within:border-olive"
        >
            <MagnifyingGlassIcon class="size-5 shrink-0" />
            <input
                v-model="search"
                type="search"
                enterkeyhint="search"
                :placeholder="t('search_expenses')"
                :aria-label="t('search_expenses')"
                class="min-w-0 flex-1 bg-transparent text-base text-ink outline-none"
                @input="onSearch"
            />
        </label>

        <div class="flex flex-wrap gap-2">
            <select
                :value="filters.type ?? ''"
                :aria-label="t('expense_type')"
                class="select h-9 w-auto shrink-0 rounded-full pr-8 text-sm font-medium"
                :class="filters.type ? 'border-ink bg-ink text-paper' : 'border-line bg-white text-ink'"
                @change="onType"
            >
                <option value="">{{ t('all_expense_types') }}</option>
                <option v-for="option in types" :key="option.id" :value="option.id">{{ option.name }}</option>
            </select>

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

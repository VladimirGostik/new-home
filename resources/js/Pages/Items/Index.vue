<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { PlusIcon } from '@heroicons/vue/24/outline';
import ItemCard from '@/Components/Items/ItemCard.vue';
import ItemFilters, { type ItemFilterOptions } from '@/Components/Items/ItemFilters.vue';
import type { Paginator } from '@/types';

const props = defineProps<{
    items: Paginator<App.Data.ItemListItemData>;
    filters: Record<string, unknown>;
    filterOptions: ItemFilterOptions;
}>();

const { t } = useI18n();

const boughtOnPage = computed(() => props.items.data.filter((item) => item.status === 'bought').length);

function goToPage(url: string | null) {
    if (url) router.get(url, {}, { preserveState: true });
}
</script>

<template>
    <Head :title="t('items')" />

    <div class="flex flex-col gap-4">
        <div class="flex items-baseline justify-between gap-4">
            <h1 class="font-display text-3xl font-semibold tracking-tight">{{ t('items') }}</h1>
            <div class="flex items-center gap-4">
                <span class="text-sm text-muted tabular-nums">{{ t('items_count', items.total) }} · {{ t('bought_count', boughtOnPage) }}</span>
                <Link
                    href="/items/create"
                    class="btn btn-primary hidden rounded-[14px] lg:inline-flex"
                >
                    <PlusIcon class="size-4.5 stroke-2" />
                    {{ t('new_item') }}
                </Link>
            </div>
        </div>

        <ItemFilters
            :filters="filters"
            :options="filterOptions"
        />

        <div
            v-if="items.data.length"
            class="grid gap-2.5 md:grid-cols-2"
        >
            <ItemCard
                v-for="item in items.data"
                :key="item.id"
                :item="item"
            />
        </div>
        <div
            v-else
            class="rounded-[18px] border border-dashed border-[#d6d0c2] bg-white p-6 text-center text-muted"
        >
            {{ t('no_items_found') }}
        </div>

        <p
            v-if="items.data.length"
            class="text-center text-[13px] text-muted"
        >{{ t('tap_check_hint') }}</p>

        <nav
            v-if="items.last_page > 1"
            :aria-label="t('pagination')"
            class="flex items-center justify-center gap-3"
        >
            <button
                type="button"
                class="btn rounded-[14px] border-line bg-white"
                :disabled="!items.prev_page_url"
                @click="goToPage(items.prev_page_url)"
            >{{ t('previous') }}</button>
            <span class="text-sm text-muted tabular-nums">{{ items.current_page }} / {{ items.last_page }}</span>
            <button
                type="button"
                class="btn rounded-[14px] border-line bg-white"
                :disabled="!items.next_page_url"
                @click="goToPage(items.next_page_url)"
            >{{ t('next') }}</button>
        </nav>
    </div>
</template>

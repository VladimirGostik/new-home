<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ItemCard from '@/Components/Items/ItemCard.vue';
import SpendHero from '@/Components/SpendHero.vue';

const props = defineProps<{
    items: App.Data.ItemListItemData[];
    summary: App.Data.SpendSummaryData;
}>();

const { t } = useI18n();

const toBuy = computed(() => props.items.filter((item) => item.status === 'planned'));
const bought = computed(() => props.items.filter((item) => item.status === 'bought'));
</script>

<template>
    <Head :title="t('my_items')" />

    <div class="flex flex-col gap-6">
        <h1 class="font-display text-3xl font-semibold tracking-tight">{{ t('my_items') }}</h1>

        <div
            v-if="items.length === 0"
            class="flex flex-col items-start gap-3 rounded-[20px] border border-dashed border-[#d6d0c2] bg-white p-6"
        >
            <p class="text-muted">{{ t('my_items_empty') }}</p>
            <Link
                href="/items?filter[assignee]=none"
                class="btn btn-primary rounded-[14px]"
            >{{ t('show_unassigned') }}</Link>
        </div>

        <template v-else>
            <SpendHero :summary="summary" />

            <section
                aria-labelledby="to-buy-heading"
                class="flex flex-col gap-2.5"
            >
                <h2
                    id="to-buy-heading"
                    class="font-display text-[22px] font-semibold"
                >{{ t('to_buy') }} <span class="text-base font-normal text-muted tabular-nums">({{ toBuy.length }})</span></h2>
                <p
                    v-if="toBuy.length === 0"
                    class="rounded-2xl bg-olive-soft p-4 text-olive-deep"
                >{{ t('all_bought') }}</p>
                <div class="grid gap-2.5 md:grid-cols-2">
                    <ItemCard
                        v-for="item in toBuy"
                        :key="item.id"
                        :item="item"
                        :show-assignee="false"
                    />
                </div>
            </section>

            <section
                v-if="bought.length"
                aria-labelledby="bought-heading"
                class="flex flex-col gap-2.5"
            >
                <h2
                    id="bought-heading"
                    class="font-display text-[22px] font-semibold"
                >{{ t('already_bought') }} <span class="text-base font-normal text-muted tabular-nums">({{ bought.length }})</span></h2>
                <div class="grid gap-2.5 md:grid-cols-2">
                    <ItemCard
                        v-for="item in bought"
                        :key="item.id"
                        :item="item"
                        :show-assignee="false"
                    />
                </div>
            </section>
        </template>
    </div>
</template>

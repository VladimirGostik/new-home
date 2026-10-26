<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatEur } from '@/Composables/useMoney';
import { formatMonthLabel } from '@/utils/date';

const props = defineProps<{
    totals: App.Data.ExpenseTotalsData;
    month: string;
}>();

const { t, locale } = useI18n();

const monthLabel = computed(() => formatMonthLabel(props.month, locale.value));
</script>

<template>
    <section class="flex flex-col gap-3 rounded-[20px] border border-line bg-white p-4">
        <div class="flex flex-col gap-1">
            <p class="text-sm font-medium text-muted">{{ t('spent_in_month', { month: monthLabel }) }}</p>
            <p class="font-display text-3xl font-semibold tabular-nums text-ink">{{ formatEur(totals.total) }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line-soft pt-3 text-sm text-muted">
            <span>
                {{ t('expenses_from_items') }}
                <span class="font-medium tabular-nums text-ink">{{ formatEur(totals.items_total) }}</span>
            </span>
            <span>
                {{ t('expenses_own') }}
                <span class="font-medium tabular-nums text-ink">{{ formatEur(totals.manual_total) }}</span>
            </span>
            <span class="ml-auto">{{ t('expenses_count', totals.count) }}</span>
        </div>
    </section>
</template>

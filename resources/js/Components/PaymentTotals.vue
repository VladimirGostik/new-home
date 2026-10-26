<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { formatEur } from '@/Composables/useMoney';

defineProps<{
    summary: Pick<App.Data.SpendSummaryData, 'total' | 'paid_total' | 'to_pay_total'>;
    tone: 'dark' | 'light';
}>();

const { t } = useI18n();
</script>

<template>
    <div
        class="grid grid-cols-3 gap-3 border-t pt-4"
        :class="tone === 'dark' ? 'border-paper/15 text-paper' : 'border-line-soft text-ink'"
    >
        <div class="flex min-w-0 flex-col gap-0.5">
            <span class="text-[13px]" :class="tone === 'dark' ? 'text-paper/70' : 'text-muted'">{{ t('total') }}</span>
            <span class="text-base font-semibold tabular-nums">{{ formatEur(summary.total) }}</span>
        </div>
        <div class="flex min-w-0 flex-col gap-0.5">
            <span class="text-[13px]" :class="tone === 'dark' ? 'text-paper/70' : 'text-muted'">{{
                t('paid_total')
            }}</span>
            <span
                class="text-base font-semibold tabular-nums"
                :class="tone === 'dark' ? 'text-oak-light' : 'text-olive-deep'"
                >{{ formatEur(summary.paid_total) }}</span
            >
        </div>
        <div class="flex min-w-0 flex-col gap-0.5">
            <span class="text-[13px]" :class="tone === 'dark' ? 'text-paper/70' : 'text-muted'">{{
                t('to_pay_total')
            }}</span>
            <span class="text-base font-semibold tabular-nums">{{ formatEur(summary.to_pay_total) }}</span>
        </div>
    </div>
</template>

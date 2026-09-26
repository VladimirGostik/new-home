<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ProgressBar from '@/Components/ProgressBar.vue';
import { boughtPercent, formatEur } from '@/Composables/useMoney';

const props = defineProps<{
    summary: App.Data.SpendSummaryData;
}>();

const { t } = useI18n();

const percent = computed(() => boughtPercent(props.summary));
</script>

<template>
    <section
        :aria-label="t('summary')"
        class="flex flex-col gap-4.5 rounded-[22px] bg-ink p-5.5 text-paper"
    >
        <div class="flex flex-col gap-1">
            <span class="text-[13px] tracking-[0.06em] text-[#cfc8b8] uppercase">{{ t('remaining_to_buy') }}</span>
            <span class="font-display text-[40px] leading-tight font-medium tracking-tight tabular-nums">{{ formatEur(summary.remaining_total) }}</span>
        </div>
        <div class="flex flex-col gap-2">
            <ProgressBar
                :value="percent"
                track="dark"
                size="md"
                :label="t('bought_percent', { n: percent })"
            />
            <span class="text-[13px] text-[#cfc8b8]">{{ t('bought_percent', { n: percent }) }}</span>
        </div>
        <div class="grid grid-cols-2 gap-3 border-t border-[#3a3a33] pt-4">
            <div class="flex flex-col gap-0.5">
                <span class="text-[13px] text-[#cfc8b8]">{{ t('planned_total') }}</span>
                <span class="text-lg font-semibold tabular-nums">{{ formatEur(summary.total) }}</span>
            </div>
            <div class="flex flex-col gap-0.5">
                <span class="text-[13px] text-[#cfc8b8]">{{ t('bought_total') }}</span>
                <span class="text-lg font-semibold text-oak-light tabular-nums">{{ formatEur(summary.bought_total) }}</span>
            </div>
        </div>
        <slot />
    </section>
</template>

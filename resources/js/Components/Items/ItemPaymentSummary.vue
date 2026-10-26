<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ProgressBar from '@/Components/ProgressBar.vue';
import { formatEur, paidPercent } from '@/Composables/useMoney';

const props = defineProps<{
    item: Pick<App.Data.ItemListItemData, 'total_price' | 'paid_total' | 'remaining' | 'overpaid'>;
}>();

const { t } = useI18n();

const hasPrice = computed(() => props.item.total_price !== null);
const percent = computed(() => paidPercent(props.item.paid_total, props.item.total_price));
</script>

<template>
    <div class="flex flex-col gap-2 rounded-[18px] border border-line bg-white p-4">
        <div class="grid grid-cols-3 gap-3">
            <div class="flex min-w-0 flex-col gap-0.5">
                <span class="text-[13px] text-muted">{{ t('price') }}</span>
                <span class="text-base font-semibold tabular-nums">{{ formatEur(item.total_price) }}</span>
            </div>
            <div class="flex min-w-0 flex-col gap-0.5">
                <span class="text-[13px] text-muted">{{ t('paid') }}</span>
                <span class="text-base font-semibold text-olive-deep tabular-nums">{{
                    formatEur(item.paid_total)
                }}</span>
            </div>
            <div v-if="hasPrice" class="flex min-w-0 flex-col gap-0.5">
                <span class="text-[13px] text-muted">{{ t('remaining_to_pay_short') }}</span>
                <span class="text-base font-semibold tabular-nums">{{ formatEur(item.remaining) }}</span>
            </div>
        </div>
        <ProgressBar
            v-if="hasPrice"
            tone="olive"
            size="md"
            :value="percent"
            :label="t('paid_percent', { n: percent })"
        />
        <p v-if="item.overpaid > 0" class="text-[13px] font-medium text-oak-deep tabular-nums">
            {{ t('overpaid_by', { amount: formatEur(item.overpaid) }) }}
        </p>
    </div>
</template>

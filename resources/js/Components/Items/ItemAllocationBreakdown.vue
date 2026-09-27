<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { ChevronDownIcon } from '@heroicons/vue/24/outline';
import { formatEur } from '@/Composables/useMoney';
import { formatQuantity } from '@/utils/quantity';

const props = withDefaults(
    defineProps<{
        allocations: App.Data.ItemAllocationData[];
        unitPrice: number | null;
        collapsible: boolean;
        highlightRoomId?: string | null;
    }>(),
    { highlightRoomId: null },
);

const { t } = useI18n();

function isHighlighted(allocation: App.Data.ItemAllocationData): boolean {
    return props.highlightRoomId !== null && allocation.room_id === props.highlightRoomId;
}
</script>

<template>
    <details v-if="collapsible" class="group">
        <summary class="flex min-h-11 cursor-pointer items-center justify-between text-[13px] font-medium text-muted">
            {{ t('allocation_breakdown') }}
            <ChevronDownIcon class="size-4 transition-transform group-open:rotate-180" />
        </summary>
        <ul class="mt-1 flex flex-col gap-1">
            <li
                v-for="allocation in allocations"
                :key="allocation.id"
                class="grid grid-cols-[minmax(0,1fr)_auto_auto] gap-x-3 text-sm tabular-nums"
                :class="isHighlighted(allocation) ? 'font-semibold text-ink' : 'text-muted'"
            >
                <span class="truncate">{{ allocation.room_name ?? t('whole_house') }}</span>
                <span>{{ formatQuantity(allocation.quantity) }}</span>
                <span>{{ allocation.line_total === null ? '–' : formatEur(allocation.line_total) }}</span>
            </li>
        </ul>
    </details>
    <section v-else :aria-label="t('allocation_breakdown')">
        <ul class="flex flex-col gap-1">
            <li
                v-for="allocation in allocations"
                :key="allocation.id"
                class="grid grid-cols-[minmax(0,1fr)_auto_auto] gap-x-3 text-sm tabular-nums"
                :class="isHighlighted(allocation) ? 'font-semibold text-ink' : 'text-muted'"
            >
                <span class="truncate">{{ allocation.room_name ?? t('whole_house') }}</span>
                <span>{{ formatQuantity(allocation.quantity) }}</span>
                <span>{{ allocation.line_total === null ? '–' : formatEur(allocation.line_total) }}</span>
            </li>
        </ul>
    </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
import { currentMonth, formatMonthLabel, shiftMonth } from '@/utils/date';

const props = defineProps<{
    month: string;
}>();

const emit = defineEmits<{
    change: [month: string];
}>();

const { t, locale } = useI18n();

const label = computed(() => formatMonthLabel(props.month, locale.value));
const isCurrent = computed(() => props.month >= currentMonth());
</script>

<template>
    <div class="flex items-center justify-between gap-2 rounded-[20px] border border-line bg-white p-1.5">
        <button
            type="button"
            class="btn btn-ghost size-11 min-h-0 rounded-[14px] p-0"
            :aria-label="t('previous_month')"
            @click="emit('change', shiftMonth(month, -1))"
        >
            <ChevronLeftIcon class="size-5" />
        </button>

        <p class="font-display text-lg font-semibold capitalize text-ink" aria-live="polite">
            {{ label }}
        </p>

        <button
            type="button"
            class="btn btn-ghost size-11 min-h-0 rounded-[14px] p-0"
            :aria-label="t('next_month')"
            :disabled="isCurrent"
            @click="emit('change', shiftMonth(month, 1))"
        >
            <ChevronRightIcon class="size-5" />
        </button>
    </div>
</template>

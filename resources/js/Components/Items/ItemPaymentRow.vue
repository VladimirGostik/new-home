<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { formatEur } from '@/Composables/useMoney';
import { formatDate } from '@/utils/date';

defineProps<{
    payment: App.Data.ExpenseData;
    canEdit: boolean;
}>();

const emit = defineEmits<{
    edit: [];
    delete: [];
}>();

const { t } = useI18n();
</script>

<template>
    <li class="flex items-center gap-2 rounded-[18px] border border-line bg-white py-2 pr-2 pl-4">
        <div class="flex min-w-0 flex-1 flex-col gap-0.5">
            <div class="flex items-baseline justify-between gap-3">
                <span class="text-[15px] font-semibold tabular-nums">{{ formatEur(payment.amount) }}</span>
                <span class="text-[13px] text-muted tabular-nums">{{ formatDate(payment.paid_at) }}</span>
            </div>
            <p v-if="payment.note" class="truncate text-[13px] text-muted">{{ payment.note }}</p>
            <p v-if="payment.created_by_name" class="truncate text-xs text-muted">{{ payment.created_by_name }}</p>
        </div>
        <template v-if="canEdit">
            <button
                type="button"
                :aria-label="t('edit_payment')"
                class="flex size-11 shrink-0 items-center justify-center rounded-xl text-muted hover:bg-paper hover:text-ink"
                @click="emit('edit')"
            >
                <PencilSquareIcon class="size-5" />
            </button>
            <button
                type="button"
                :aria-label="t('delete_payment')"
                class="flex size-11 shrink-0 items-center justify-center rounded-xl text-error hover:bg-paper"
                @click="emit('delete')"
            >
                <TrashIcon class="size-5" />
            </button>
        </template>
    </li>
</template>

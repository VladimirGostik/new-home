<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { formatEur } from '@/Composables/useMoney';

const props = defineProps<{
    expense: App.Data.ExpenseData;
    canEdit: boolean;
}>();

const emit = defineEmits<{
    edit: [];
    delete: [];
}>();

const { t } = useI18n();
const page = usePage();

const href = computed(() => `/items/${props.expense.item_id}?return=${encodeURIComponent(page.url)}`);
</script>

<template>
    <Link
        v-if="expense.item_id"
        :href="href"
        class="flex min-h-14 items-center gap-3 rounded-[14px] px-3 py-2 hover:bg-paper"
    >
        <div class="flex min-w-0 flex-1 flex-col gap-0.5">
            <span class="truncate font-medium text-ink">{{ expense.display_name }}</span>
            <span class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted">
                <span class="rounded-full bg-olive-soft px-2 py-0.5 text-xs font-medium text-olive-deep">{{
                    t('item_payment_chip')
                }}</span>
                <span v-if="expense.note" class="truncate">{{ expense.note }}</span>
            </span>
        </div>
        <span class="shrink-0 text-right font-semibold tabular-nums text-ink">{{ formatEur(expense.amount) }}</span>
    </Link>

    <div v-else class="flex min-h-14 items-center gap-3 rounded-[14px] px-3 py-2">
        <div class="flex min-w-0 flex-1 flex-col gap-0.5">
            <span class="truncate font-medium text-ink">{{ expense.display_name }}</span>
            <span v-if="expense.note" class="truncate text-sm text-muted">{{ expense.note }}</span>
        </div>
        <span class="shrink-0 text-right font-semibold tabular-nums text-ink">{{ formatEur(expense.amount) }}</span>
        <div v-if="canEdit" class="flex shrink-0 items-center">
            <button
                type="button"
                class="btn btn-ghost size-11 min-h-0 rounded-full p-0 text-muted"
                :aria-label="t('edit')"
                @click="emit('edit')"
            >
                <PencilSquareIcon class="size-5" />
            </button>
            <button
                type="button"
                class="btn btn-ghost size-11 min-h-0 rounded-full p-0 text-muted"
                :aria-label="t('delete')"
                @click="emit('delete')"
            >
                <TrashIcon class="size-5" />
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ExpenseRow from '@/Components/Expenses/ExpenseRow.vue';
import { formatEur } from '@/Composables/useMoney';
import { formatDayLabel } from '@/utils/date';

const props = defineProps<{
    expenses: App.Data.ExpenseData[];
    canEdit: boolean;
    filtered: boolean;
}>();

const emit = defineEmits<{
    edit: [expense: App.Data.ExpenseData];
    delete: [expense: App.Data.ExpenseData];
}>();

const { t, locale } = useI18n();

const days = computed(() => {
    const groups = new Map<string, App.Data.ExpenseData[]>();
    for (const expense of props.expenses) {
        const group = groups.get(expense.paid_at) ?? [];
        group.push(expense);
        groups.set(expense.paid_at, group);
    }

    return [...groups.entries()].map(([date, rows]) => ({
        date,
        label: formatDayLabel(date, locale.value),
        total: rows.reduce((sum, row) => sum + row.amount, 0),
        rows,
    }));
});
</script>

<template>
    <p
        v-if="expenses.length === 0"
        class="rounded-[20px] border border-dashed border-line px-4 py-10 text-center text-muted"
    >
        {{ filtered ? t('expenses_empty_filtered') : t('expenses_empty') }}
    </p>

    <div v-else class="flex flex-col gap-4">
        <section v-for="day in days" :key="day.date" :aria-label="day.label" class="flex flex-col gap-1">
            <header class="flex items-baseline justify-between gap-3 px-3">
                <h2 class="text-sm font-semibold text-muted first-letter:uppercase">{{ day.label }}</h2>
                <span class="text-sm tabular-nums text-muted">{{ formatEur(day.total) }}</span>
            </header>

            <div class="flex flex-col rounded-[20px] border border-line bg-white p-1.5">
                <ExpenseRow
                    v-for="expense in day.rows"
                    :key="expense.id"
                    :expense="expense"
                    :can-edit="canEdit"
                    @edit="emit('edit', expense)"
                    @delete="emit('delete', expense)"
                />
            </div>
        </section>
    </div>
</template>

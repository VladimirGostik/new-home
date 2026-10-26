<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { PlusIcon } from '@heroicons/vue/24/outline';
import ExpenseMonthNav from '@/Components/Expenses/ExpenseMonthNav.vue';
import ExpenseTotals from '@/Components/Expenses/ExpenseTotals.vue';
import ExpenseFilters from '@/Components/Expenses/ExpenseFilters.vue';
import ExpenseList from '@/Components/Expenses/ExpenseList.vue';
import ExpenseFormModal from '@/Components/Expenses/ExpenseFormModal.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { useExpenseQuery } from '@/Composables/useExpenseQuery';
import { formatEur } from '@/Composables/useMoney';

const props = defineProps<{
    month: string;
    expenses: App.Data.ExpenseData[];
    totals: App.Data.ExpenseTotalsData;
    filters: App.Data.ExpenseIndexFilterData;
    filterOptions: { types: { id: string; name: string }[] };
}>();

const { t } = useI18n();
const page = usePage();
const can = computed(() => page.props.can);

const { visit } = useExpenseQuery(() => props.filters);

const filtered = computed(() => !!props.filters.search || !!props.filters.type);

const formTarget = ref<App.Data.ExpenseData | 'new' | null>(null);
const editingExpense = computed(() => (formTarget.value === 'new' ? null : formTarget.value));

const deleteConfirm = useDeleteConfirm<App.Data.ExpenseData>({
    resolveUrl: (expense) => `/expenses/${expense.id}`,
    getTitle: () => t('delete_expense_title'),
    getDescription: (expense) =>
        t('delete_expense_text', { name: expense.display_name, amount: formatEur(expense.amount) }),
});
</script>

<template>
    <Head :title="t('expenses')" />

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-4">
            <h1 class="font-display text-3xl font-semibold tracking-tight">{{ t('expenses') }}</h1>
            <button
                v-if="can.editItems"
                type="button"
                class="btn h-11 rounded-[14px] border-0 bg-ink px-4 text-paper hover:bg-ink-soft"
                @click="formTarget = 'new'"
            >
                <PlusIcon class="size-4.5 stroke-2" />
                {{ t('add_expense') }}
            </button>
        </div>

        <ExpenseMonthNav :month="month" @change="visit({ month: $event })" />

        <ExpenseTotals :totals="totals" :month="month" />

        <ExpenseFilters :filters="filters" :types="filterOptions.types" @change="visit" />

        <ExpenseList
            :expenses="expenses"
            :can-edit="!!can.editItems"
            :filtered="filtered"
            @edit="formTarget = $event"
            @delete="deleteConfirm.openModal"
        />
    </div>

    <ExpenseFormModal
        v-if="formTarget"
        :key="editingExpense?.id ?? 'new'"
        :expense="editingExpense"
        @close="formTarget = null"
    />

    <ConfirmDeleteModal
        :is-open="deleteConfirm.state.isOpen"
        :title="deleteConfirm.getModalTitle()"
        :description="deleteConfirm.getModalDescription()"
        @cancel="deleteConfirm.closeModal"
        @confirm="deleteConfirm.confirmDelete"
    />
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { PlusIcon } from '@heroicons/vue/24/outline';
import ItemPaymentRow from './ItemPaymentRow.vue';
import ItemPaymentSummary from './ItemPaymentSummary.vue';
import ExpenseFormModal from '@/Components/Expenses/ExpenseFormModal.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';

const props = defineProps<{
    item: App.Data.ItemListItemData;
    payments: App.Data.ExpenseData[];
}>();

const { t } = useI18n();
const page = usePage();

const can = computed(() => page.props.can);
const visible = computed(() => !!can.value.editItems || props.payments.length > 0);

type FormTarget = App.Data.ExpenseData | 'new' | null;
const formTarget = ref<FormTarget>(null);

const deleteConfirm = useDeleteConfirm<App.Data.ExpenseData>({
    resolveUrl: (payment) => `/expenses/${payment.id}`,
    getTitle: () => t('delete_payment_title'),
    getDescription: () => t('delete_payment_text'),
});
</script>

<template>
    <section v-if="visible" aria-labelledby="payments-heading" class="flex flex-col gap-3">
        <div class="flex items-center justify-between gap-2">
            <h2 id="payments-heading" class="font-display text-[22px] font-semibold">
                {{ t('payments') }} <span class="text-muted">({{ payments.length }})</span>
            </h2>
            <button
                v-if="can.editItems"
                type="button"
                class="btn btn-primary btn-sm h-11 rounded-xl"
                @click="formTarget = 'new'"
            >
                <PlusIcon class="size-4" />
                {{ t('add_payment') }}
            </button>
        </div>

        <ItemPaymentSummary :item="item" />

        <ul v-if="payments.length" class="flex flex-col gap-2">
            <ItemPaymentRow
                v-for="payment in payments"
                :key="payment.id"
                :payment="payment"
                :can-edit="!!can.editItems"
                @edit="formTarget = payment"
                @delete="deleteConfirm.openModal(payment)"
            />
        </ul>
        <div v-else class="rounded-[18px] border border-dashed border-line p-6 text-center">
            <p class="text-sm text-muted">{{ t('payments_empty') }}</p>
        </div>
    </section>

    <ExpenseFormModal
        v-if="formTarget"
        :key="formTarget === 'new' ? 'new' : formTarget.id"
        :item-id="item.id"
        :expense="formTarget === 'new' ? null : formTarget"
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

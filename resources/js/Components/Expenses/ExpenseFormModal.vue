<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';
import PriceInput from '@/Components/Forms/PriceInput.vue';
import DateInput from '@/Components/Forms/DateInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import { todayInputValue } from '@/utils/date';

const props = defineProps<{
    expense: App.Data.ExpenseData | null;
    itemId?: string | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

interface ExpenseFormData {
    title?: string;
    amount: number | null;
    paid_at: string;
    note: string;
}

const isEditing = computed(() => !!props.expense);
const isPayment = computed(() => !!props.itemId || !!props.expense?.item_id);

const method = isEditing.value ? 'put' : 'post';
const url = props.expense
    ? `/expenses/${props.expense.id}`
    : props.itemId
      ? `/items/${props.itemId}/payments`
      : '/expenses';

const initial: ExpenseFormData = {
    amount: props.expense?.amount ?? null,
    paid_at: props.expense?.paid_at ?? todayInputValue(),
    note: props.expense?.note ?? '',
};
if (!isPayment.value) initial.title = props.expense?.title ?? '';

const form = useForm<ExpenseFormData>(method, url, initial);
form.transform((data: ExpenseFormData) => ({ ...data, note: data.note.trim() === '' ? null : data.note }));

const heading = computed(() => {
    if (isPayment.value) return isEditing.value ? t('edit_payment') : t('new_payment');
    return isEditing.value ? t('edit_expense') : t('new_expense');
});

const dialog = ref<HTMLDialogElement | null>(null);
onMounted(() => dialog.value?.showModal());

function submit() {
    form.submit({ preserveScroll: true, preserveState: true, onSuccess: () => emit('close') });
}
</script>

<template>
    <dialog ref="dialog" class="modal modal-bottom sm:modal-middle" @cancel.prevent="emit('close')">
        <div class="modal-box">
            <h3 class="font-display text-xl">{{ heading }}</h3>

            <FormProvider :form="form">
                <form novalidate class="mt-4 flex flex-col gap-4" @submit.prevent="submit">
                    <TextInput v-if="!isPayment" field="title" required autofocus :label="t('expense_title')" />
                    <PriceInput v-model="form.amount" required :label="t('amount')" :error="form.errors.amount" />
                    <DateInput
                        v-model="form.paid_at"
                        required
                        :max="todayInputValue()"
                        :label="t('paid_at_date')"
                        :error="form.errors.paid_at"
                    />
                    <TextareaInput v-model="form.note" :rows="2" :label="t('note')" :error="form.errors.note" />

                    <div class="modal-action">
                        <button type="button" class="btn h-12 rounded-2xl border-line bg-white" @click="emit('close')">
                            {{ t('cancel') }}
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary h-12 flex-1 rounded-2xl"
                            :disabled="form.processing"
                        >
                            <span v-if="form.processing" class="loading loading-spinner loading-sm" />
                            {{ isEditing ? t('save_changes') : t('save') }}
                        </button>
                    </div>
                </form>
            </FormProvider>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button :aria-label="t('close')" @click="emit('close')">close</button>
        </form>
    </dialog>
</template>

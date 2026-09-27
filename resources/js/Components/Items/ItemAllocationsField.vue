<script lang="ts">
export interface AllocationRow {
    key: string;
    room_id: string | null;
    quantity: number | null;
}

let seq = 0;

export function newAllocationRow(roomId: string | null, quantity: number | null): AllocationRow {
    seq += 1;
    return { key: `alloc-${seq}`, room_id: roomId, quantity };
}
</script>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { PlusIcon } from '@heroicons/vue/24/outline';
import ItemAllocationRow from './ItemAllocationRow.vue';
import type { SelectOption } from '@/Components/Forms/SelectInput.vue';
import { formatEur } from '@/Composables/useMoney';
import { formatQuantity, sumLineTotals, sumQuantity } from '@/utils/quantity';

const props = defineProps<{
    modelValue: AllocationRow[];
    roomOptions: { id: string; name: string }[];
    unitPrice: number | null;
    errors: Partial<Record<string, string>>;
}>();

const emit = defineEmits<{
    'update:modelValue': [rows: AllocationRow[]];
}>();

const { t } = useI18n();

const allChoices = computed<SelectOption[]>(() => [
    { value: '', label: t('whole_house') },
    ...props.roomOptions.map((room) => ({ value: room.id, label: room.name })),
]);

const totalQuantity = computed(() => sumQuantity(props.modelValue.map((row) => row.quantity)));
const total = computed(() =>
    sumLineTotals(
        props.unitPrice,
        props.modelValue.map((row) => row.quantity),
    ),
);
const canAdd = computed(() => props.modelValue.length < allChoices.value.length && props.modelValue.length < 50);

function usedValues(excludeIndex?: number): Set<string> {
    return new Set(props.modelValue.filter((_, index) => index !== excludeIndex).map((row) => row.room_id ?? ''));
}

function optionsFor(index: number): SelectOption[] {
    const ownValue = props.modelValue[index]?.room_id ?? '';
    const used = usedValues(index);
    return allChoices.value.filter((choice) => String(choice.value) === ownValue || !used.has(String(choice.value)));
}

function labelFor(roomId: string | null): string {
    const value = roomId ?? '';
    return allChoices.value.find((choice) => String(choice.value) === value)?.label ?? t('whole_house');
}

function updateRow(index: number, patch: Partial<Pick<AllocationRow, 'room_id' | 'quantity'>>) {
    emit(
        'update:modelValue',
        props.modelValue.map((row, i) => (i === index ? { ...row, ...patch } : row)),
    );
}

function addRow() {
    const used = usedValues();
    const nextChoice = allChoices.value.find((choice) => !used.has(String(choice.value)));
    const roomId = nextChoice && nextChoice.value !== '' ? String(nextChoice.value) : null;
    emit('update:modelValue', [...props.modelValue, newAllocationRow(roomId, null)]);
}

function removeRow(index: number) {
    emit(
        'update:modelValue',
        props.modelValue.filter((_, i) => i !== index),
    );
}
</script>

<template>
    <fieldset class="flex flex-col gap-2">
        <legend class="mb-2 text-sm font-semibold">{{ t('rooms_and_quantities') }}</legend>

        <div class="flex flex-col gap-2">
            <ItemAllocationRow
                v-for="(row, index) in modelValue"
                :key="row.key"
                :room-id="row.room_id"
                :quantity="row.quantity"
                :room-options="optionsFor(index)"
                :room-error="errors[`allocations.${index}.room_id`]"
                :quantity-error="errors[`allocations.${index}.quantity`]"
                :removable="modelValue.length > 1"
                :room-label="labelFor(row.room_id)"
                @update:room-id="(value) => updateRow(index, { room_id: value })"
                @update:quantity="(value) => updateRow(index, { quantity: value })"
                @remove="removeRow(index)"
            />
        </div>

        <button type="button" class="btn btn-outline" :disabled="!canAdd" @click="addRow">
            <PlusIcon class="size-4" />
            {{ t('add_allocation_row') }}
        </button>

        <p v-if="errors.allocations" class="text-sm text-error">{{ errors.allocations }}</p>

        <div class="flex items-baseline justify-between rounded-[14px] bg-olive-soft px-3.5 py-3 text-olive-deep">
            <span class="flex flex-col gap-0.5">
                <span class="text-sm font-medium">{{ t('total') }}</span>
                <span v-if="modelValue.length > 1" class="text-[13px]">{{
                    t('total_quantity_value', { qty: formatQuantity(totalQuantity) })
                }}</span>
            </span>
            <span class="font-display text-[22px] font-semibold tabular-nums" aria-live="polite">{{
                total === null ? t('no_price') : formatEur(total)
            }}</span>
        </div>
    </fieldset>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { TrashIcon } from '@heroicons/vue/24/outline';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import QuantityInput from '@/Components/Forms/QuantityInput.vue';

defineProps<{
    roomId: string | null;
    quantity: number | null;
    roomOptions: SelectOption[];
    roomError?: string | null;
    quantityError?: string | null;
    removable: boolean;
    roomLabel: string;
}>();

const emit = defineEmits<{
    'update:roomId': [value: string | null];
    'update:quantity': [value: number | null];
    remove: [];
}>();

const { t } = useI18n();

function onRoomChange(value: string) {
    emit('update:roomId', value === '' ? null : value);
}
</script>

<template>
    <div class="flex items-start gap-2 rounded-[14px] border border-line bg-white p-3">
        <div class="grid flex-1 grid-cols-[minmax(0,1fr)_7rem] gap-2">
            <SelectInput
                :model-value="roomId ?? ''"
                :label="t('room')"
                :options="roomOptions"
                :error="roomError"
                @update:model-value="onRoomChange"
            />
            <QuantityInput
                :model-value="quantity"
                :label="t('allocation_quantity')"
                required
                :error="quantityError"
                @update:model-value="(value) => emit('update:quantity', value)"
            />
        </div>
        <button
            v-if="removable"
            type="button"
            :aria-label="t('remove_allocation_row', { room: roomLabel })"
            class="mt-6 flex size-11 shrink-0 items-center justify-center rounded-xl text-error hover:bg-line-soft"
            @click="emit('remove')"
        >
            <TrashIcon class="size-5" />
        </button>
    </div>
</template>

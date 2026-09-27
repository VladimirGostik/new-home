<script setup lang="ts">
import FormField from './FormField.vue';
import { useDecimalInput } from '@/Composables/useDecimalInput';
import { formatQuantityInput } from '@/utils/quantity';

const props = withDefaults(
    defineProps<{
        modelValue: number | null;
        label: string;
        error?: string | null;
        required?: boolean;
        disabled?: boolean;
    }>(),
    { error: null, required: false, disabled: false },
);

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const { text, onFocus, onInput, onBlur } = useDecimalInput(
    () => props.modelValue,
    (value) => emit('update:modelValue', value),
    formatQuantityInput,
);
</script>

<template>
    <FormField :label="label" :error="error" :required="required">
        <label class="input w-full tabular-nums">
            <input
                :value="text"
                type="text"
                inputmode="decimal"
                autocomplete="off"
                :disabled="disabled"
                :aria-invalid="error ? 'true' : undefined"
                :aria-required="required ? 'true' : undefined"
                @focus="onFocus"
                @input="onInput"
                @blur="onBlur"
            />
        </label>
    </FormField>
</template>

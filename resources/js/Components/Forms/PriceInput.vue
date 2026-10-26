<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import FormField from './FormField.vue';
import { useDecimalInput } from '@/Composables/useDecimalInput';

const props = withDefaults(
    defineProps<{
        modelValue: number | null;
        label: string;
        error?: string | null;
        disabled?: boolean;
        required?: boolean;
    }>(),
    { error: null, disabled: false, required: false },
);

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const { t } = useI18n();

function formatPrice(value: number | null): string {
    return value === null ? '' : value.toFixed(2).replace('.', ',');
}

const { text, onFocus, onInput, onBlur } = useDecimalInput(
    () => props.modelValue,
    (value) => emit('update:modelValue', value),
    formatPrice,
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
                :placeholder="t('no_price')"
                :disabled="disabled"
                :aria-required="required ? 'true' : undefined"
                :aria-invalid="error ? 'true' : undefined"
                @focus="onFocus"
                @input="onInput"
                @blur="onBlur"
            />
            <span class="text-muted">€</span>
        </label>
    </FormField>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import FormField from './FormField.vue';

const props = withDefaults(
    defineProps<{
        modelValue: number | null;
        label: string;
        error?: string | null;
        disabled?: boolean;
    }>(),
    { error: null, disabled: false },
);

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const { t } = useI18n();

function format(value: number | null): string {
    return value === null ? '' : value.toFixed(2).replace('.', ',');
}

/** "1 290,5" / "1290.50" → 1290.5; empty → null; anything unparseable → null. */
function parse(raw: string): number | null {
    const normalized = raw.replace(/\s/g, '').replace(',', '.');
    if (normalized === '') return null;
    const value = Number(normalized);
    return Number.isFinite(value) ? value : null;
}

// While typing, the text is left exactly as typed; ",00" is only added on blur / external change.
const text = ref(format(props.modelValue));
const focused = ref(false);

watch(
    () => props.modelValue,
    (value) => {
        if (focused.value && parse(text.value) === value) return;
        text.value = format(value);
    },
);

function onInput(event: Event) {
    text.value = (event.target as HTMLInputElement).value;
    emit('update:modelValue', parse(text.value));
}

function onBlur() {
    focused.value = false;
    text.value = format(parse(text.value));
}
</script>

<template>
    <FormField
        :label="label"
        :error="error"
    >
        <label class="input w-full tabular-nums">
            <input
                :value="text"
                type="text"
                inputmode="decimal"
                autocomplete="off"
                :placeholder="t('no_price')"
                :disabled="disabled"
                :aria-invalid="error ? 'true' : undefined"
                @focus="focused = true"
                @input="onInput"
                @blur="onBlur"
            />
            <span class="text-muted">€</span>
        </label>
    </FormField>
</template>

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

const text = ref(props.modelValue !== null ? props.modelValue.toFixed(2).replace('.', ',') : '');

watch(
    () => props.modelValue,
    (value) => {
        text.value = value !== null ? value.toFixed(2).replace('.', ',') : '';
    },
);

function onInput(event: Event) {
    const raw = (event.target as HTMLInputElement).value.replace(/\s/g, '').replace(',', '.');
    emit('update:modelValue', raw === '' ? null : Number(raw));
}
</script>

<template>
    <FormField
        :label="label"
        :error="error"
    >
        <label class="input w-full tabular-nums">
            <input
                v-model="text"
                type="text"
                inputmode="decimal"
                autocomplete="off"
                :placeholder="t('no_price')"
                :disabled="disabled"
                :aria-invalid="error ? 'true' : undefined"
                @input="onInput"
            />
            <span class="text-muted">€</span>
        </label>
    </FormField>
</template>

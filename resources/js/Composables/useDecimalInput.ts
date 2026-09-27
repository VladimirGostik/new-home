import { ref, watch } from 'vue';
import { parseDecimal } from '@/utils/quantity';

export function useDecimalInput(
    source: () => number | null,
    emit: (value: number | null) => void,
    format: (value: number | null) => string,
) {
    const text = ref(format(source()));
    const focused = ref(false);

    watch(source, (value) => {
        if (focused.value && parseDecimal(text.value) === value) return;
        text.value = format(value);
    });

    function onFocus() {
        focused.value = true;
    }

    function onInput(event: Event) {
        text.value = (event.target as HTMLInputElement).value;
        emit(parseDecimal(text.value));
    }

    function onBlur() {
        focused.value = false;
        text.value = format(parseDecimal(text.value));
    }

    return { text, onFocus, onInput, onBlur };
}

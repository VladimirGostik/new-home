<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { CheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    itemId: string;
    itemName: string;
}>();

const status = defineModel<string>('status', { required: true });

const { t } = useI18n();

const busy = ref(false);
const bought = computed(() => status.value === 'bought');

function toggle() {
    if (busy.value) return;
    const previous = status.value;
    status.value = bought.value ? 'planned' : 'bought';
    busy.value = true;

    router.patch(
        `/items/${props.itemId}/status`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => (status.value = previous),
            onFinish: () => (busy.value = false),
        },
    );
}
</script>

<template>
    <button
        type="button"
        :aria-pressed="bought"
        :aria-label="bought ? t('mark_as_planned_named', { name: itemName }) : t('mark_as_bought_named', { name: itemName })"
        :disabled="busy"
        class="flex size-12 shrink-0 items-center justify-center rounded-full transition-all active:scale-90"
        :class="bought ? 'bg-olive text-white' : 'border-[1.5px] border-line text-muted hover:border-olive hover:text-olive'"
        @click="toggle"
    >
        <CheckIcon class="size-5.5 stroke-[2.4]" />
    </button>
</template>

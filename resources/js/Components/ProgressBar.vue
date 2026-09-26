<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        value: number;
        tone?: 'oak' | 'olive';
        track?: 'light' | 'dark';
        size?: 'sm' | 'md';
        label?: string;
    }>(),
    { tone: 'oak', track: 'light', size: 'sm', label: undefined },
);

const width = computed(() => `${Math.min(100, Math.max(0, props.value))}%`);
</script>

<template>
    <div
        role="progressbar"
        :aria-valuenow="Math.round(value)"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-label="label"
        class="overflow-hidden rounded-full"
        :class="[size === 'md' ? 'h-2' : 'h-1.5', track === 'dark' ? 'bg-[#3a3a33]' : 'bg-line-soft']"
    >
        <div
            class="h-full rounded-full transition-[width] duration-500"
            :class="tone === 'olive' ? 'bg-olive' : 'bg-oak'"
            :style="{ width }"
        />
    </div>
</template>

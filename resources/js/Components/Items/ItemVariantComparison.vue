<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { SparklesIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { renderMarkdown } from '@/utils/markdown';
import { formatDatetime } from '@/utils/date';

const props = defineProps<{
    comparison: App.Data.ItemVariantComparisonData;
}>();

const { t } = useI18n();

const html = computed(() => renderMarkdown(props.comparison.text));
const generatedAt = computed(() => formatDatetime(props.comparison.generated_at));
</script>

<template>
    <section
        aria-labelledby="variant-comparison-heading"
        class="flex flex-col gap-3 rounded-[20px] border border-line bg-white p-4.5"
    >
        <div class="flex items-center gap-2">
            <SparklesIcon class="size-5 text-olive-deep" aria-hidden="true" />
            <h2 id="variant-comparison-heading" class="font-display text-[22px] font-semibold">
                {{ t('variant_comparison_title') }}
            </h2>
        </div>

        <p class="text-sm text-muted">
            <time :datetime="comparison.generated_at">{{
                t('variant_comparison_generated_at', { date: generatedAt })
            }}</time>
        </p>

        <div v-if="comparison.is_stale" role="status" class="alert alert-warning alert-soft text-sm">
            <ExclamationTriangleIcon class="size-5 shrink-0" aria-hidden="true" />
            <span>{{ t('variant_comparison_stale') }}</span>
        </div>

        <!-- eslint-disable-next-line vue/no-v-html -->
        <div class="comparison-prose min-w-0 break-words" v-html="html" />
    </section>
</template>

<style scoped>
.comparison-prose :deep(p),
.comparison-prose :deep(ul),
.comparison-prose :deep(ol),
.comparison-prose :deep(blockquote),
.comparison-prose :deep(pre),
.comparison-prose :deep(table) {
    margin-block: calc(var(--spacing) * 3);
}

.comparison-prose :deep(*:first-child) {
    margin-block-start: 0;
}

.comparison-prose :deep(*:last-child) {
    margin-block-end: 0;
}

.comparison-prose {
    font-size: var(--text-sm);
    line-height: 1.6;
}

@media (width >= 48rem) {
    .comparison-prose {
        font-size: var(--text-base);
    }
}

.comparison-prose :deep(h1),
.comparison-prose :deep(h2),
.comparison-prose :deep(h3),
.comparison-prose :deep(h4) {
    font-family: var(--font-display);
    font-weight: 600;
    color: var(--color-ink);
}

.comparison-prose :deep(h1) {
    font-size: var(--text-xl);
}

.comparison-prose :deep(h2) {
    font-size: var(--text-lg);
}

.comparison-prose :deep(h3),
.comparison-prose :deep(h4) {
    font-size: var(--text-base);
}

.comparison-prose :deep(ul) {
    list-style-type: disc;
    padding-inline-start: calc(var(--spacing) * 5);
}

.comparison-prose :deep(ol) {
    list-style-type: decimal;
    padding-inline-start: calc(var(--spacing) * 5);
}

.comparison-prose :deep(strong) {
    font-weight: 600;
}

.comparison-prose :deep(a) {
    color: var(--color-olive-deep);
    text-decoration: underline;
}

.comparison-prose :deep(blockquote) {
    border-inline-start: 2px solid var(--color-line);
    padding-inline-start: calc(var(--spacing) * 3);
    color: var(--color-muted);
}

.comparison-prose :deep(code) {
    background-color: var(--color-line-soft);
    border-radius: var(--radius-field);
    padding-inline: calc(var(--spacing) * 1);
}

.comparison-prose :deep(pre) {
    overflow-x: auto;
    background-color: var(--color-line-soft);
    border-radius: var(--radius-field);
    padding: calc(var(--spacing) * 3);
}

.comparison-prose :deep(pre) code {
    background-color: transparent;
    padding-inline: 0;
}

.comparison-prose :deep(table) {
    display: block;
    overflow-x: auto;
    border-collapse: collapse;
}

.comparison-prose :deep(th),
.comparison-prose :deep(td) {
    border: 1px solid var(--color-line);
    padding: calc(var(--spacing) * 2);
    text-align: start;
}

.comparison-prose :deep(hr) {
    border: none;
    border-top: 1px solid var(--color-line);
}
</style>

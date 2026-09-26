<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { PhotoIcon } from '@heroicons/vue/24/outline';
import { formatEur } from '@/Composables/useMoney';
import ItemBoughtToggle from './ItemBoughtToggle.vue';

const props = withDefaults(
    defineProps<{
        item: App.Data.ItemListItemData;
        showRoom?: boolean;
        showAssignee?: boolean;
    }>(),
    { showRoom: true, showAssignee: true },
);

const { t } = useI18n();
const page = usePage();

// Optimistic status so the ✓ reacts instantly in the shop; server props win once they arrive.
const status = ref(props.item.status);
watch(
    () => props.item.status,
    (value) => (status.value = value),
);
const bought = computed(() => status.value === 'bought');

const PRIORITY_DOT: Record<string, string> = {
    high: 'bg-[#b4532a]',
    medium: 'bg-oak',
    low: 'bg-[#a8a294]',
};

const href = computed(() => `/items/${props.item.id}?return=${encodeURIComponent(page.url)}`);

const meta = computed(() => {
    const parts: string[] = [];
    if (props.showRoom) parts.push(props.item.room_name ?? t('whole_house'));
    if (props.showAssignee) parts.push(props.item.assigned_user_name ?? t('unassigned_lower'));
    return parts.join(' · ');
});

const variantChip = computed(() => {
    if (props.item.selected_variant_id !== null) {
        return { label: t('variant_chosen_short'), class: 'bg-olive-soft text-olive-deep' };
    }
    if (props.item.variants_count > 0) {
        return { label: t('variants_count', props.item.variants_count), class: 'bg-oak-soft text-oak-ink' };
    }
    return null;
});

const price = computed(() => {
    if (props.item.unit_price === null) return null;
    if (props.item.quantity > 1) {
        return `${props.item.quantity} × ${formatEur(props.item.unit_price)} = ${formatEur(props.item.total_price)}`;
    }
    return formatEur(props.item.total_price);
});
</script>

<template>
    <article
        class="flex items-center gap-3 rounded-[18px] border border-line p-3 transition-colors"
        :class="bought ? 'bg-line-soft' : 'bg-white'"
    >
        <Link
            :href="href"
            class="flex min-w-0 flex-1 items-center gap-3"
        >
            <span class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-oak-soft text-oak-deep">
                <img
                    v-if="item.photo_thumb_url"
                    :src="item.photo_thumb_url"
                    :alt="item.name"
                    loading="lazy"
                    class="size-full object-cover"
                    :class="{ 'opacity-60': bought }"
                />
                <PhotoIcon
                    v-else
                    class="size-6"
                />
            </span>
            <span class="flex min-w-0 flex-1 flex-col gap-1">
                <span class="flex items-center gap-1.5">
                    <span
                        class="size-2 shrink-0 rounded-full"
                        :class="PRIORITY_DOT[item.priority]"
                        :title="t(`priority_${item.priority}`)"
                    />
                    <span
                        class="truncate text-base font-semibold"
                        :class="{ 'text-muted line-through': bought }"
                    >{{ item.name }}</span>
                </span>
                <span
                    v-if="meta || variantChip"
                    class="flex items-center gap-1.5 truncate text-[13px] text-muted"
                >
                    <span
                        v-if="meta"
                        class="truncate"
                    >{{ meta }}</span>
                    <span
                        v-if="variantChip"
                        class="inline-flex h-5.5 shrink-0 items-center rounded-full px-2 text-xs"
                        :class="variantChip.class"
                    >{{ variantChip.label }}</span>
                </span>
                <span
                    v-if="price"
                    class="text-[15px] font-semibold tabular-nums"
                >{{ price }}</span>
                <span
                    v-else
                    class="text-[13px] font-medium text-oak-deep"
                >{{ t('no_price') }}</span>
            </span>
        </Link>

        <ItemBoughtToggle
            v-model:status="status"
            :item-id="item.id"
            :item-name="item.name"
        />
    </article>
</template>

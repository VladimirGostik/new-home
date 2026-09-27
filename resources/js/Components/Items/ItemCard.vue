<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { PhotoIcon } from '@heroicons/vue/24/outline';
import { formatEur } from '@/Composables/useMoney';
import { formatQuantity } from '@/utils/quantity';
import ItemBoughtToggle from './ItemBoughtToggle.vue';
import ItemAllocationBreakdown from './ItemAllocationBreakdown.vue';

const props = withDefaults(
    defineProps<{
        item: App.Data.ItemListItemData;
        contextRoomId?: string | null;
        showAssignee?: boolean;
    }>(),
    { contextRoomId: null, showAssignee: true },
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

const roomCount = computed(() => props.item.allocations.length);
const isMultiRoom = computed(() => roomCount.value > 1);
const contextAllocation = computed(
    () => props.item.allocations.find((allocation) => allocation.room_id === props.contextRoomId) ?? null,
);
const showRoomChip = computed(() => isMultiRoom.value && !props.contextRoomId);

const roomText = computed(() => {
    if (props.contextRoomId) {
        return isMultiRoom.value ? t('also_in_other_rooms', roomCount.value - 1) : '';
    }
    return isMultiRoom.value
        ? t('rooms_count', roomCount.value)
        : (props.item.allocations[0]?.room_name ?? t('whole_house'));
});

const roomChip = computed(() =>
    showRoomChip.value ? { label: roomText.value, class: 'bg-oak-soft text-oak-ink' } : null,
);

const meta = computed(() => {
    const parts: string[] = [];
    if (!showRoomChip.value && roomText.value) parts.push(roomText.value);
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
    if (props.contextRoomId) {
        const allocation = contextAllocation.value;
        if (!allocation) return null;
        if (allocation.quantity === 1) return formatEur(allocation.line_total);
        return `${formatQuantity(allocation.quantity)} × ${formatEur(props.item.unit_price)} = ${formatEur(allocation.line_total)}`;
    }
    if (props.item.quantity !== 1) {
        return `${formatQuantity(props.item.quantity)} × ${formatEur(props.item.unit_price)} = ${formatEur(props.item.total_price)}`;
    }
    return formatEur(props.item.total_price);
});
</script>

<template>
    <article
        class="min-w-0 flex flex-col gap-2 rounded-[18px] border border-line p-3 transition-colors"
        :class="bought ? 'bg-line-soft' : 'bg-white'"
    >
        <div class="flex items-center gap-3">
            <Link :href="href" class="flex min-w-0 flex-1 items-center gap-3">
                <span
                    class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-oak-soft text-oak-deep"
                >
                    <img
                        v-if="item.photo_thumb_url"
                        :src="item.photo_thumb_url"
                        :alt="item.name"
                        loading="lazy"
                        class="size-full object-cover"
                        :class="{ 'opacity-60': bought }"
                    />
                    <PhotoIcon v-else class="size-6" />
                </span>
                <span class="flex min-w-0 flex-1 flex-col gap-1">
                    <span class="flex items-center gap-1.5">
                        <span
                            class="size-2 shrink-0 rounded-full"
                            :class="PRIORITY_DOT[item.priority]"
                            :title="t(`priority_${item.priority}`)"
                        />
                        <span class="truncate text-base font-semibold" :class="{ 'text-muted line-through': bought }">{{
                            item.name
                        }}</span>
                    </span>
                    <span
                        v-if="meta || roomChip || variantChip"
                        class="flex items-center gap-1.5 truncate text-[13px] text-muted"
                    >
                        <span v-if="meta" class="truncate">{{ meta }}</span>
                        <span
                            v-if="roomChip"
                            class="inline-flex h-5.5 shrink-0 items-center rounded-full px-2 text-xs"
                            :class="roomChip.class"
                            >{{ roomChip.label }}</span
                        >
                        <span
                            v-if="variantChip"
                            class="inline-flex h-5.5 shrink-0 items-center rounded-full px-2 text-xs"
                            :class="variantChip.class"
                            >{{ variantChip.label }}</span
                        >
                    </span>
                    <span v-if="price" class="text-[15px] font-semibold tabular-nums">{{ price }}</span>
                    <span v-else class="text-[13px] font-medium text-oak-deep">{{ t('no_price') }}</span>
                </span>
            </Link>

            <ItemBoughtToggle v-model:status="status" :item-id="item.id" :item-name="item.name" />
        </div>

        <ItemAllocationBreakdown
            v-if="isMultiRoom"
            collapsible
            :allocations="item.allocations"
            :unit-price="item.unit_price"
            :highlight-room-id="contextRoomId"
        />
    </article>
</template>

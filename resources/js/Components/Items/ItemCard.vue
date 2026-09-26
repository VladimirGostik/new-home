<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { CheckIcon, PhotoIcon } from '@heroicons/vue/24/outline';
import { formatEur } from '@/Composables/useMoney';

const props = withDefaults(
    defineProps<{
        item: App.Data.ItemListItemData;
        showRoom?: boolean;
        showAssignee?: boolean;
    }>(),
    { showRoom: true, showAssignee: true },
);

const { t } = useI18n();

// Optimistic status so the ✓ reacts instantly in the shop; server props win once they arrive.
const status = ref(props.item.status);
watch(
    () => props.item.status,
    (value) => (status.value = value),
);
const busy = ref(false);
const bought = computed(() => status.value === 'bought');

const PRIORITY_DOT: Record<string, string> = {
    high: 'bg-[#b4532a]',
    medium: 'bg-oak',
    low: 'bg-[#a8a294]',
};

const meta = computed(() => {
    const parts: string[] = [];
    if (props.showRoom) parts.push(props.item.room_name ?? t('whole_house'));
    if (props.showAssignee) parts.push(props.item.assigned_user_name ?? t('unassigned_lower'));
    return parts.join(' · ');
});

const price = computed(() => {
    if (props.item.unit_price === null) return null;
    if (props.item.quantity > 1) {
        return `${props.item.quantity} × ${formatEur(props.item.unit_price)} = ${formatEur(props.item.total_price)}`;
    }
    return formatEur(props.item.total_price);
});

function toggle() {
    if (busy.value) return;
    const previous = status.value;
    status.value = bought.value ? 'planned' : 'bought';
    busy.value = true;

    router.patch(
        `/items/${props.item.id}/status`,
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
    <article
        class="flex items-center gap-3 rounded-[18px] border border-line p-3 transition-colors"
        :class="bought ? 'bg-[#f4f1ea]' : 'bg-white'"
    >
        <Link
            :href="`/items/${item.id}/edit`"
            class="flex min-w-0 flex-1 items-center gap-3"
        >
            <span class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-oak-soft text-[#a0703f]">
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
                    v-if="meta"
                    class="truncate text-[13px] text-muted"
                >{{ meta }}</span>
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

        <button
            type="button"
            :aria-pressed="bought"
            :aria-label="bought ? t('mark_as_planned_named', { name: item.name }) : t('mark_as_bought_named', { name: item.name })"
            :disabled="busy"
            class="flex size-12 shrink-0 items-center justify-center rounded-full transition-all active:scale-90"
            :class="bought ? 'bg-olive text-white' : 'border-[1.5px] border-[#d6d0c2] bg-white text-[#b8b2a4] hover:border-olive hover:text-olive'"
            @click="toggle"
        >
            <CheckIcon class="size-5.5 stroke-[2.4]" />
        </button>
    </article>
</template>

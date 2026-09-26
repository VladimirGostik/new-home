<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { PlusIcon, ChevronUpIcon, ChevronDownIcon, PencilSquareIcon, TrashIcon, HomeIcon } from '@heroicons/vue/24/outline';
import ProgressBar from '@/Components/ProgressBar.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { boughtPercent, formatEur } from '@/Composables/useMoney';
import type { Paginator } from '@/types';

const props = defineProps<{
    rooms: Paginator<App.Data.RoomListItemData>;
    filters?: Record<string, unknown>;
    wholeHouse: App.Data.SpendSummaryData | null;
}>();

const { t } = useI18n();

// Local copy so reordering moves cards instantly; server props win after save.
const ordered = ref([...props.rooms.data]);
watch(
    () => props.rooms.data,
    (rooms) => (ordered.value = [...rooms]),
);

const house = computed(() => props.wholeHouse ?? { items_count: 0, bought_count: 0, unpriced_count: 0, total: 0, bought_total: 0, remaining_total: 0 });

function move(index: number, delta: number) {
    const target = index + delta;
    if (target < 0 || target >= ordered.value.length) return;
    const next = [...ordered.value];
    [next[index], next[target]] = [next[target], next[index]];
    ordered.value = next;

    router.put(
        '/rooms/reorder',
        { ids: next.map((room) => room.id) },
        { preserveScroll: true, preserveState: true, onError: () => (ordered.value = [...props.rooms.data]) },
    );
}

const TILE_TONES = ['bg-olive-soft text-olive-deep', 'bg-oak-soft text-oak-ink'];

const deleteConfirm = useDeleteConfirm<App.Data.RoomListItemData>({
    resolveUrl: (room) => `/rooms/${room.id}`,
    getTitle: (room) => t('delete_room_title', { name: room.name }),
    getDescription: (room) =>
        room.summary.items_count > 0
            ? t('delete_room_moves_items', room.summary.items_count)
            : t('delete_room_empty'),
});
</script>

<template>
    <Head :title="t('rooms')" />

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-4">
            <h1 class="font-display text-3xl font-semibold tracking-tight">{{ t('rooms') }}</h1>
            <Link
                href="/rooms/create"
                class="btn h-11 rounded-[14px] border-0 bg-ink px-4 text-paper hover:bg-ink-soft"
            >
                <PlusIcon class="size-4.5 stroke-2" />
                {{ t('add') }}
            </Link>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <article
                v-for="(room, index) in ordered"
                :key="room.id"
                class="flex flex-col gap-3 rounded-[20px] border border-line bg-white p-4"
            >
                <div class="flex items-start gap-3">
                    <Link
                        :href="`/rooms/${room.id}`"
                        class="flex min-w-0 flex-1 items-start gap-3"
                    >
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl font-display text-xl font-semibold"
                            :class="TILE_TONES[index % 2]"
                            aria-hidden="true"
                        >{{ room.name.charAt(0) }}</span>
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span class="truncate text-[17px] font-semibold">{{ room.name }}</span>
                            <span
                                v-if="room.description"
                                class="line-clamp-1 text-[13px] text-muted"
                            >{{ room.description }}</span>
                        </span>
                    </Link>
                    <div class="-mr-1 flex shrink-0 flex-col">
                        <button
                            type="button"
                            class="flex size-9 items-center justify-center rounded-lg text-[#a8a294] hover:bg-paper hover:text-ink disabled:opacity-30"
                            :aria-label="t('move_up', { name: room.name })"
                            :disabled="index === 0"
                            @click="move(index, -1)"
                        >
                            <ChevronUpIcon class="size-5" />
                        </button>
                        <button
                            type="button"
                            class="flex size-9 items-center justify-center rounded-lg text-[#a8a294] hover:bg-paper hover:text-ink disabled:opacity-30"
                            :aria-label="t('move_down', { name: room.name })"
                            :disabled="index === ordered.length - 1"
                            @click="move(index, 1)"
                        >
                            <ChevronDownIcon class="size-5" />
                        </button>
                    </div>
                </div>

                <div class="flex items-baseline justify-between">
                    <span class="text-[13px] text-muted">{{ t('items_count', room.summary.items_count) }} · {{ t('bought_percent', { n: boughtPercent(room.summary) }) }}</span>
                    <span class="font-display text-xl font-semibold tabular-nums">{{ formatEur(room.summary.total) }}</span>
                </div>
                <ProgressBar
                    tone="olive"
                    :value="boughtPercent(room.summary)"
                    :label="t('bought_percent', { n: boughtPercent(room.summary) })"
                />

                <div class="-mb-1 flex justify-end gap-1 border-t border-line-soft pt-2">
                    <Link
                        :href="`/rooms/${room.id}/edit`"
                        class="btn btn-ghost btn-sm h-10 rounded-xl text-muted"
                    >
                        <PencilSquareIcon class="size-4.5" />
                        {{ t('edit') }}
                    </Link>
                    <button
                        type="button"
                        class="btn btn-ghost btn-sm h-10 rounded-xl text-error"
                        @click="deleteConfirm.openModal(room)"
                    >
                        <TrashIcon class="size-4.5" />
                        {{ t('delete') }}
                    </button>
                </div>
            </article>
        </div>

        <Link
            href="/items?filter[room]=house"
            class="flex items-center gap-3 rounded-[20px] border border-dashed border-[#d6d0c2] bg-[#f4f1ea] p-4 transition-colors hover:bg-white"
        >
            <span class="flex size-11 items-center justify-center rounded-xl bg-ink text-paper">
                <HomeIcon class="size-5" />
            </span>
            <span class="flex flex-1 flex-col">
                <span class="text-[17px] font-semibold">{{ t('whole_house') }}</span>
                <span class="text-[13px] text-muted">{{ t('items_without_room', house.items_count) }}</span>
            </span>
            <span class="font-display text-xl font-semibold tabular-nums">{{ formatEur(house.total) }}</span>
        </Link>
    </div>

    <ConfirmDeleteModal
        :is-open="deleteConfirm.state.isOpen"
        :title="deleteConfirm.getModalTitle()"
        :description="deleteConfirm.getModalDescription()"
        @cancel="deleteConfirm.closeModal"
        @confirm="deleteConfirm.confirmDelete"
    />
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ChevronLeftIcon, PencilSquareIcon, PlusIcon } from '@heroicons/vue/24/outline';
import ItemCard from '@/Components/Items/ItemCard.vue';
import ProgressBar from '@/Components/ProgressBar.vue';
import { boughtPercent, formatEur } from '@/Composables/useMoney';

const props = defineProps<{
    room: App.Data.RoomListItemData;
    items: App.Data.ItemListItemData[];
}>();

const { t } = useI18n();

const percent = computed(() => boughtPercent(props.room.summary));
const addHref = computed(
    () => `/items/create?room=${props.room.id}&return=${encodeURIComponent(`/rooms/${props.room.id}`)}`,
);
</script>

<template>
    <Head :title="room.name" />

    <div class="flex flex-col gap-5">
        <div class="-ml-2 flex items-center gap-2">
            <Link
                href="/rooms"
                :aria-label="t('back')"
                class="flex size-11 items-center justify-center rounded-xl hover:bg-white"
            >
                <ChevronLeftIcon class="size-5.5 stroke-2" />
            </Link>
            <h1 class="min-w-0 flex-1 truncate font-display text-3xl font-semibold tracking-tight">{{ room.name }}</h1>
            <Link
                :href="`/rooms/${room.id}/edit`"
                :aria-label="t('edit_room')"
                class="flex size-11 items-center justify-center rounded-xl text-muted hover:bg-white hover:text-ink"
            >
                <PencilSquareIcon class="size-5" />
            </Link>
        </div>

        <p v-if="room.description" class="-mt-2 text-muted">{{ room.description }}</p>

        <section
            :aria-label="t('summary')"
            class="flex flex-col gap-3 rounded-[20px] border border-line bg-white p-4.5"
        >
            <div class="flex items-baseline justify-between gap-3">
                <span class="text-sm text-muted">{{ t('room_total') }}</span>
                <span class="font-display text-[28px] font-semibold tabular-nums">{{
                    formatEur(room.summary.total)
                }}</span>
            </div>
            <ProgressBar tone="olive" size="md" :value="percent" :label="t('bought_percent', { n: percent })" />
            <div class="flex justify-between text-[13px] text-muted tabular-nums">
                <span>{{ t('amount_bought', { amount: formatEur(room.summary.bought_total) }) }}</span>
                <span>{{ t('amount_remaining', { amount: formatEur(room.summary.remaining_total) }) }}</span>
            </div>
            <p v-if="room.summary.unpriced_count > 0" class="text-[13px] text-oak-deep">
                {{ t('unpriced_not_counted', room.summary.unpriced_count) }}
            </p>
        </section>

        <div class="flex items-baseline justify-between">
            <h2 class="font-display text-[22px] font-semibold">
                {{ t('items') }} <span class="text-base font-normal text-muted tabular-nums">({{ items.length }})</span>
            </h2>
            <Link :href="addHref" class="btn btn-primary btn-sm hidden h-10 rounded-xl lg:inline-flex">
                <PlusIcon class="size-4 stroke-2" />
                {{ t('add_item') }}
            </Link>
        </div>

        <div v-if="items.length" class="grid gap-2.5 md:grid-cols-2">
            <ItemCard v-for="item in items" :key="item.id" :item="item" :context-room-id="room.id" />
        </div>
        <div
            v-else
            class="flex flex-col items-start gap-3 rounded-[18px] border border-dashed border-[#d6d0c2] bg-white p-5"
        >
            <p class="text-muted">{{ t('room_empty') }}</p>
            <Link :href="addHref" class="btn btn-primary rounded-[14px]">{{ t('add_item') }}</Link>
        </div>
    </div>
</template>

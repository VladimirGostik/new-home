<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { CheckBadgeIcon, ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';
import { formatEur } from '@/Composables/useMoney';
import { formatQuantity } from '@/utils/quantity';
import ItemBoughtToggle from './ItemBoughtToggle.vue';
import ItemAllocationBreakdown from './ItemAllocationBreakdown.vue';

const props = defineProps<{
    item: App.Data.ItemListItemData;
}>();

const { t } = useI18n();

const status = ref(props.item.status);
watch(
    () => props.item.status,
    (value) => (status.value = value),
);

const price = computed(() => {
    if (props.item.unit_price === null) return null;
    if (props.item.quantity !== 1) {
        return `${formatQuantity(props.item.quantity)} × ${formatEur(props.item.unit_price)} = ${formatEur(props.item.total_price)}`;
    }
    return formatEur(props.item.total_price);
});

function hostname(url: string): string {
    try {
        return new URL(url).hostname;
    } catch {
        return url;
    }
}
</script>

<template>
    <div class="flex flex-col gap-3 rounded-[20px] border border-line bg-white p-4.5">
        <a v-if="item.photo_url" :href="item.photo_url" target="_blank" rel="noopener">
            <img :src="item.photo_url" :alt="item.name" class="aspect-4/3 w-full rounded-2xl object-cover" />
        </a>

        <p v-if="item.selected_variant_id" class="flex items-center gap-1.5 text-sm font-medium text-olive-deep">
            <CheckBadgeIcon class="size-4.5" />
            {{ t('selected_variant_named', { name: item.selected_variant_name }) }}
        </p>

        <div class="flex items-center justify-between gap-3">
            <span v-if="price" class="font-display text-[28px] tabular-nums">{{ price }}</span>
            <span v-else class="font-display text-[28px] tabular-nums text-oak-deep">{{ t('no_price') }}</span>

            <ItemBoughtToggle v-model:status="status" :item-id="item.id" :item-name="item.name" />
        </div>

        <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 text-sm">
            <template v-if="item.allocations.length > 1">
                <dt class="text-muted">{{ t('rooms_and_quantities') }}</dt>
                <dd class="col-span-2">
                    <ItemAllocationBreakdown
                        :allocations="item.allocations"
                        :unit-price="item.unit_price"
                        :collapsible="false"
                    />
                </dd>
            </template>
            <template v-else>
                <dt class="text-muted">{{ t('room') }}</dt>
                <dd>{{ item.allocations[0]?.room_name ?? t('whole_house') }}</dd>
            </template>

            <dt class="text-muted">{{ t('who_buys_it') }}</dt>
            <dd>{{ item.assigned_user_name ?? t('unassigned') }}</dd>

            <template v-if="item.url">
                <dt class="text-muted">{{ t('product_link') }}</dt>
                <dd>
                    <a
                        :href="item.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-11 items-center gap-1 text-olive-deep underline"
                    >
                        {{ hostname(item.url) }}
                        <ArrowTopRightOnSquareIcon class="size-4" />
                    </a>
                </dd>
            </template>
        </dl>

        <p v-if="item.note" class="text-sm whitespace-pre-line text-muted">
            <span class="mb-0.5 block font-medium text-ink">{{ t('description') }}</span>
            {{ item.note }}
        </p>
    </div>
</template>

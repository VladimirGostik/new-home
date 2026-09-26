<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { PlusIcon } from '@heroicons/vue/24/outline';
import ItemVariantCard from './ItemVariantCard.vue';
import { useVariantVoting } from '@/Composables/useVariantVoting';
import { useVariantSelection } from '@/Composables/useVariantSelection';

const props = defineProps<{
    itemId: string;
    quantity: number;
    variants: App.Data.ItemVariantListItemData[];
}>();

const emit = defineEmits<{
    add: [];
    edit: [variant: App.Data.ItemVariantListItemData];
    delete: [variant: App.Data.ItemVariantListItemData];
}>();

const { t } = useI18n();
const page = usePage();

const can = computed(() => page.props.can);

const { displayVariants, leaderIds, isTie, pending, vote } = useVariantVoting(
    () => props.itemId,
    () => props.variants,
);
const { busyId, select, unselect } = useVariantSelection(() => props.itemId);
</script>

<template>
    <section
        aria-labelledby="variants-heading"
        class="flex flex-col gap-3"
    >
        <div class="flex items-center justify-between gap-2">
            <h2
                id="variants-heading"
                class="font-display text-[22px] font-semibold"
            >
                {{ t('variants') }} <span class="text-muted">({{ variants.length }})</span>
            </h2>
            <button
                v-if="can.editItems"
                type="button"
                class="btn btn-primary btn-sm h-11 rounded-xl"
                @click="emit('add')"
            >
                <PlusIcon class="size-4" />
                {{ t('add_variant') }}
            </button>
        </div>

        <p
            v-if="variants.length >= 2"
            class="-mt-2 text-sm text-muted"
        >{{ t('variants_vote_hint') }}</p>

        <div
            v-if="variants.length === 0"
            class="flex flex-col items-center gap-3 rounded-[18px] border border-dashed border-line p-6 text-center"
        >
            <p class="text-sm text-muted">{{ t('variants_empty') }}</p>
            <button
                v-if="can.editItems"
                type="button"
                class="btn btn-primary btn-sm h-11 rounded-xl"
                @click="emit('add')"
            >
                {{ t('add_variant') }}
            </button>
        </div>

        <div
            v-else
            class="grid gap-2.5 md:grid-cols-2"
        >
            <ItemVariantCard
                v-for="v in displayVariants"
                :key="v.id"
                :variant="v"
                :quantity="quantity"
                :is-leader="leaderIds.has(v.id)"
                :is-tie="isTie"
                :vote-pending="pending"
                :select-busy="busyId === v.id"
                :can-edit="!!can.editItems"
                :can-delete="!!can.deleteItems"
                @vote="vote(v.id)"
                @select="select(v.id)"
                @unselect="unselect()"
                @edit="emit('edit', v)"
                @delete="emit('delete', v)"
            />
        </div>
    </section>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ChevronLeftIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';
import ItemSummaryCard from '@/Components/Items/ItemSummaryCard.vue';
import ItemVariantsSection from '@/Components/Items/ItemVariantsSection.vue';
import ItemVariantComparison from '@/Components/Items/ItemVariantComparison.vue';
import ItemVariantFormModal from '@/Components/Items/ItemVariantFormModal.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { useReturnTo } from '@/Composables/useReturnTo';

const props = defineProps<{
    item: App.Data.ItemListItemData;
    variants: App.Data.ItemVariantListItemData[];
    comparison: App.Data.ItemVariantComparisonData | null;
}>();

const { t } = useI18n();
const page = usePage();

const can = computed(() => page.props.can);
const returnTo = useReturnTo();
const editHref = computed(() => `/items/${props.item.id}/edit?return=${encodeURIComponent(page.url)}`);

type FormTarget = App.Data.ItemVariantListItemData | 'new' | null;
const formTarget = ref<FormTarget>(null);

function openCreate() {
    formTarget.value = 'new';
}

function openEdit(variant: App.Data.ItemVariantListItemData) {
    formTarget.value = variant;
}

function closeForm() {
    formTarget.value = null;
}

const deleteConfirm = useDeleteConfirm<App.Data.ItemVariantListItemData>({
    resolveUrl: (variant) => `/items/${props.item.id}/variants/${variant.id}`,
    getTitle: () => t('delete_variant_title'),
    getDescription: (variant) => t('delete_variant_text', { name: variant.name }),
});
</script>

<template>
    <Head :title="item.name" />

    <div class="flex flex-col gap-5">
        <div class="-ml-2 flex items-center gap-2">
            <Link
                :href="returnTo"
                :aria-label="t('back')"
                class="flex size-11 items-center justify-center rounded-xl hover:bg-white"
            >
                <ChevronLeftIcon class="size-5.5 stroke-2" />
            </Link>
            <h1 class="min-w-0 flex-1 truncate font-display text-3xl font-semibold tracking-tight">{{ item.name }}</h1>
            <Link
                v-if="can.editItems"
                :href="editHref"
                :aria-label="t('edit_item')"
                class="flex size-11 items-center justify-center rounded-xl text-muted hover:bg-white hover:text-ink"
            >
                <PencilSquareIcon class="size-5" />
            </Link>
        </div>

        <div class="flex flex-col gap-5 lg:grid lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:items-start lg:gap-6">
            <div class="lg:sticky lg:top-9">
                <ItemSummaryCard :item="item" />
            </div>

            <div class="flex min-w-0 flex-col gap-5">
                <ItemVariantsSection
                    :item-id="item.id"
                    :allocations="item.allocations"
                    :variants="variants"
                    @add="openCreate"
                    @edit="openEdit"
                    @delete="deleteConfirm.openModal"
                />

                <ItemVariantComparison v-if="comparison" :comparison="comparison" />
            </div>
        </div>
    </div>

    <ItemVariantFormModal
        v-if="formTarget"
        :key="formTarget === 'new' ? 'new' : formTarget.id"
        :item-id="item.id"
        :variant="formTarget === 'new' ? null : formTarget"
        @close="closeForm"
    />

    <ConfirmDeleteModal
        :is-open="deleteConfirm.state.isOpen"
        :title="deleteConfirm.getModalTitle()"
        :description="deleteConfirm.getModalDescription()"
        @cancel="deleteConfirm.closeModal"
        @confirm="deleteConfirm.confirmDelete"
    />
</template>

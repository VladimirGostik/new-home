<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ChevronLeftIcon, TrashIcon, MinusIcon, PlusIcon } from '@heroicons/vue/24/outline';
import TextInput from '@/Components/Forms/TextInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import FormField from '@/Components/Forms/FormField.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import PriceInput from '@/Components/Forms/PriceInput.vue';
import FileUploadInput, { type InitialFile } from '@/Components/Forms/FileUploadInput.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { useReturnTo } from '@/Composables/useReturnTo';
import { formatEur } from '@/Composables/useMoney';

interface OptionItem {
    id: string;
    name: string;
}

const props = defineProps<{
    item?: App.Data.ItemListItemData;
    roomOptions: OptionItem[];
    userOptions: OptionItem[];
    statusOptions: OptionItem[];
    priorityOptions: OptionItem[];
}>();

const { t } = useI18n();

const isEditing = computed(() => !!props.item);
const locked = computed(() => !!props.item?.selected_variant_id);

const returnTo = useReturnTo();
const query = new URLSearchParams(window.location.search);

const roomSelectOptions = computed(() => [
    { value: '', label: t('whole_house') },
    ...props.roomOptions.map((room) => ({ value: room.id, label: room.name })),
]);

interface ItemFormData {
    name: string;
    note: string;
    room_id: string | null;
    unit_price: number | null;
    quantity: number;
    url: string;
    assigned_user_id: string | null;
    status: string;
    priority: string;
    photo_uuid: string | null;
    remove_photo: boolean;
    return_to: string;
}

const form = useForm<ItemFormData>(
    isEditing.value ? 'put' : 'post',
    isEditing.value ? `/items/${props.item!.id}` : '/items',
    {
        name: props.item?.name ?? '',
        note: props.item?.note ?? '',
        room_id: props.item?.room_id ?? query.get('room') ?? null,
        unit_price: props.item?.unit_price ?? null,
        quantity: props.item?.quantity ?? 1,
        url: props.item?.url ?? '',
        assigned_user_id: props.item?.assigned_user_id ?? null,
        status: props.item?.status ?? 'planned',
        priority: props.item?.priority ?? 'medium',
        photo_uuid: null,
        remove_photo: false,
        return_to: returnTo,
    },
);

const total = computed(() =>
    form.unit_price === null || Number.isNaN(form.unit_price) ? null : Math.round(form.unit_price * form.quantity * 100) / 100,
);

const bought = computed({
    get: () => form.status === 'bought',
    set: (value: boolean) => (form.status = value ? 'bought' : 'planned'),
});

function stepQuantity(delta: number) {
    form.quantity = Math.min(9999, Math.max(1, (form.quantity || 1) + delta));
}

const photoUuid = ref<string | null>(null);
const initialPhoto = computed<InitialFile[]>(() => {
    if (!props.item?.photo_url) return [];
    return [{ uuid: props.item.photo_uuid ?? '', name: t('photo'), url: props.item.photo_url }];
});

function onPhotoUpdate(value: string | string[] | null) {
    const uuid = Array.isArray(value) ? (value[0] ?? null) : value;
    photoUuid.value = uuid;
    form.photo_uuid = uuid;
    form.remove_photo = uuid === null;
}

function onRoomChange(value: string) {
    form.room_id = value === '' ? null : value;
}

const PRIORITY_ORDER = ['low', 'medium', 'high'];
const priorities = computed(() =>
    [...props.priorityOptions].sort((a, b) => PRIORITY_ORDER.indexOf(a.id) - PRIORITY_ORDER.indexOf(b.id)),
);

const deleteConfirm = useDeleteConfirm<App.Data.ItemListItemData>({
    resolveUrl: (item) => `/items/${item.id}`,
    getTitle: () => t('delete_item_title'),
    getDescription: (item) => t('delete_item_text', { name: item.name }),
});

function submit() {
    form.submit();
}
</script>

<template>
    <Head :title="isEditing ? t('edit_item') : t('new_item')" />

    <FormProvider :form="form">
        <form
            novalidate
            class="mx-auto flex max-w-2xl flex-col gap-4.5"
            @submit.prevent="submit"
        >
            <div class="-ml-2 flex items-center gap-2">
                <Link
                    :href="returnTo"
                    :aria-label="t('back')"
                    class="flex size-11 items-center justify-center rounded-xl hover:bg-white"
                >
                    <ChevronLeftIcon class="size-5.5 stroke-2" />
                </Link>
                <h1 class="flex-1 font-display text-[22px] font-semibold lg:text-3xl">{{ isEditing ? t('edit_item') : t('new_item') }}</h1>
                <button
                    v-if="item"
                    type="button"
                    :aria-label="t('delete_item_title')"
                    class="flex size-11 items-center justify-center rounded-xl text-error hover:bg-white"
                    @click="deleteConfirm.openModal(item)"
                >
                    <TrashIcon class="size-5" />
                </button>
            </div>

            <p
                v-if="locked && item"
                class="rounded-[14px] bg-olive-soft p-3.5 text-sm text-olive-deep"
            >
                {{ t('fields_from_selected_variant', { name: item.selected_variant_name }) }}
                <Link
                    :href="`/items/${item.id}`"
                    class="inline-flex min-h-11 items-center font-semibold underline"
                >{{ t('edit_variants_link') }}</Link>
            </p>

            <FileUploadInput
                :model-value="photoUuid"
                :initial-files="initialPhoto"
                accept="image/jpeg,image/png,image/webp,image/heic"
                :error="form.errors.photo_uuid"
                :disabled="locked"
                @update:model-value="onPhotoUpdate"
            />

            <TextInput
                field="name"
                :label="t('item_name')"
                required
                autocomplete="off"
            />

            <SelectInput
                :model-value="form.room_id ?? ''"
                :label="t('room')"
                :options="roomSelectOptions"
                :error="form.errors.room_id"
                @update:model-value="onRoomChange"
            />

            <div class="grid grid-cols-2 gap-3">
                <PriceInput
                    v-model="form.unit_price"
                    :label="t('unit_price')"
                    :error="form.errors.unit_price"
                    :disabled="locked"
                />

                <FormField
                    :label="t('pieces_count')"
                    :error="form.errors.quantity"
                >
                    <div class="flex h-(--size) items-center justify-between rounded-field border border-line bg-white px-1 [--size:calc(var(--size-field,0.25rem)*10)]">
                        <button
                            type="button"
                            :aria-label="t('less_pieces')"
                            class="flex size-10 items-center justify-center rounded-[10px] bg-[#f4f1ea] disabled:opacity-40"
                            :disabled="form.quantity <= 1"
                            @click="stepQuantity(-1)"
                        >
                            <MinusIcon class="size-4.5 stroke-2" />
                        </button>
                        <span
                            class="text-[17px] font-semibold tabular-nums"
                            aria-live="polite"
                        >{{ form.quantity }}</span>
                        <button
                            type="button"
                            :aria-label="t('more_pieces')"
                            class="flex size-10 items-center justify-center rounded-[10px] bg-[#f4f1ea]"
                            @click="stepQuantity(1)"
                        >
                            <PlusIcon class="size-4.5 stroke-2" />
                        </button>
                    </div>
                </FormField>
            </div>

            <div class="flex items-baseline justify-between rounded-[14px] bg-olive-soft px-3.5 py-3 text-olive-deep">
                <span class="text-sm font-medium">{{ t('total') }}</span>
                <span class="font-display text-[22px] font-semibold tabular-nums">{{ total === null ? t('no_price') : formatEur(total) }}</span>
            </div>

            <fieldset class="flex flex-col gap-2">
                <legend class="mb-2 text-sm font-semibold">{{ t('who_buys_it') }}</legend>
                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="option in [...userOptions, { id: '', name: t('nobody') }]"
                        :key="option.id || 'nobody'"
                        class="flex h-11 cursor-pointer items-center rounded-full px-4 text-[15px] font-medium transition-colors has-focus-visible:outline-2 has-focus-visible:outline-olive"
                        :class="(form.assigned_user_id ?? '') === option.id ? 'bg-ink text-paper' : 'border border-line bg-white'"
                    >
                        <input
                            type="radio"
                            name="assigned_user_id"
                            class="sr-only"
                            :value="option.id"
                            :checked="(form.assigned_user_id ?? '') === option.id"
                            @change="form.assigned_user_id = option.id || null"
                        />
                        {{ option.name }}
                    </label>
                </div>
                <p
                    v-if="form.errors.assigned_user_id"
                    class="text-sm text-error"
                >{{ form.errors.assigned_user_id }}</p>
            </fieldset>

            <fieldset>
                <legend class="mb-2 text-sm font-semibold">{{ t('priority') }}</legend>
                <div class="grid grid-cols-3 gap-1 rounded-[14px] bg-line-soft p-1">
                    <label
                        v-for="option in priorities"
                        :key="option.id"
                        class="flex h-10 cursor-pointer items-center justify-center rounded-[10px] text-[15px] font-medium transition-all has-focus-visible:outline-2 has-focus-visible:outline-olive"
                        :class="form.priority === option.id ? 'bg-white text-ink shadow-[0_1px_3px_rgba(26,26,23,0.12)]' : 'text-muted'"
                    >
                        <input
                            v-model="form.priority"
                            type="radio"
                            name="priority"
                            class="sr-only"
                            :value="option.id"
                        />
                        {{ option.name }}
                    </label>
                </div>
            </fieldset>

            <label class="flex cursor-pointer items-center justify-between rounded-[14px] border border-line bg-white p-3.5">
                <span class="flex flex-col gap-0.5">
                    <span class="text-[15px] font-semibold">{{ t('bought') }}</span>
                    <span class="text-[13px] text-muted">{{ t('bought_hint') }}</span>
                </span>
                <input
                    v-model="bought"
                    type="checkbox"
                    class="toggle toggle-primary toggle-lg"
                />
            </label>

            <TextInput
                field="url"
                type="url"
                :label="t('product_link')"
                placeholder="https://"
                inputmode="url"
                :disabled="locked"
            />

            <TextareaInput
                v-model="form.note"
                :label="t('note')"
                :rows="3"
                :error="form.errors.note"
            />

            <div class="pb-safe fixed inset-x-0 bottom-0 z-30 border-t border-line bg-paper px-4 pt-3 lg:static lg:border-0 lg:bg-transparent lg:p-0">
                <div class="mx-auto flex max-w-2xl gap-3">
                    <Link
                        :href="returnTo"
                        class="btn hidden h-13.5 rounded-2xl border-line bg-white px-6 lg:inline-flex"
                    >{{ t('cancel') }}</Link>
                    <button
                        type="submit"
                        class="btn btn-primary h-13.5 flex-1 rounded-2xl text-base"
                        :disabled="form.processing"
                    >
                        <span
                            v-if="form.processing"
                            class="loading loading-spinner loading-sm"
                        />
                        {{ isEditing ? t('save_changes') : t('add_item') }}
                    </button>
                </div>
            </div>
        </form>
    </FormProvider>

    <ConfirmDeleteModal
        :is-open="deleteConfirm.state.isOpen"
        :title="deleteConfirm.getModalTitle()"
        :description="deleteConfirm.getModalDescription()"
        @cancel="deleteConfirm.closeModal"
        @confirm="deleteConfirm.confirmDelete"
    />
</template>

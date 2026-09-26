<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';
import PriceInput from '@/Components/Forms/PriceInput.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import FileUploadInput, { type InitialFile } from '@/Components/Forms/FileUploadInput.vue';

const props = defineProps<{
    itemId: string;
    variant: App.Data.ItemVariantListItemData | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

interface VariantFormData {
    name: string;
    unit_price: number | null;
    url: string;
    photo_uuid: string | null;
    remove_photo: boolean;
}

const isEditing = computed(() => !!props.variant);

const form = useForm<VariantFormData>(
    isEditing.value ? 'put' : 'post',
    isEditing.value ? `/items/${props.itemId}/variants/${props.variant!.id}` : `/items/${props.itemId}/variants`,
    {
        name: props.variant?.name ?? '',
        unit_price: props.variant?.unit_price ?? null,
        url: props.variant?.url ?? '',
        photo_uuid: null,
        remove_photo: false,
    },
);

const photoUuid = ref<string | null>(null);
const initialPhoto = computed<InitialFile[]>(() => {
    if (!props.variant?.photo_url) return [];
    return [{ uuid: props.variant.photo_uuid ?? '', name: t('photo'), url: props.variant.photo_url }];
});

function onPhotoUpdate(value: string | string[] | null) {
    const uuid = Array.isArray(value) ? (value[0] ?? null) : value;
    photoUuid.value = uuid;
    form.photo_uuid = uuid;
    form.remove_photo = uuid === null;
}

const dialog = ref<HTMLDialogElement | null>(null);
onMounted(() => dialog.value?.showModal());

function submit() {
    form.submit({ preserveScroll: true, preserveState: true, onSuccess: () => emit('close') });
}
</script>

<template>
    <dialog
        ref="dialog"
        class="modal modal-bottom sm:modal-middle"
        @cancel.prevent="emit('close')"
    >
        <div class="modal-box">
            <h3 class="font-display text-xl">{{ isEditing ? t('edit_variant') : t('new_variant') }}</h3>

            <p
                v-if="variant?.is_selected"
                class="mt-2 rounded-[14px] bg-olive-soft p-3 text-sm text-olive-deep"
            >{{ t('variant_edit_resyncs_item') }}</p>

            <FormProvider :form="form">
                <form
                    novalidate
                    class="mt-4 flex flex-col gap-4"
                    @submit.prevent="submit"
                >
                    <TextInput
                        field="name"
                        required
                        autofocus
                        :label="t('variant_name')"
                    />
                    <PriceInput
                        v-model="form.unit_price"
                        :label="t('unit_price')"
                        :error="form.errors.unit_price"
                    />
                    <TextInput
                        field="url"
                        type="url"
                        inputmode="url"
                        placeholder="https://"
                        :label="t('product_link')"
                    />
                    <FileUploadInput
                        :model-value="photoUuid"
                        :initial-files="initialPhoto"
                        accept="image/jpeg,image/png,image/webp,image/heic"
                        :error="form.errors.photo_uuid"
                        @update:model-value="onPhotoUpdate"
                    />

                    <div class="modal-action">
                        <button
                            type="button"
                            class="btn h-12 rounded-2xl border-line bg-white"
                            @click="emit('close')"
                        >{{ t('cancel') }}</button>
                        <button
                            type="submit"
                            class="btn btn-primary h-12 flex-1 rounded-2xl"
                            :disabled="form.processing"
                        >
                            <span
                                v-if="form.processing"
                                class="loading loading-spinner loading-sm"
                            />
                            {{ isEditing ? t('save_changes') : t('add_variant') }}
                        </button>
                    </div>
                </form>
            </FormProvider>
        </div>
        <form
            method="dialog"
            class="modal-backdrop"
        >
            <button :aria-label="t('close')" @click="emit('close')">close</button>
        </form>
    </dialog>
</template>

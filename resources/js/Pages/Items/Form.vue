<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import NumberInput from '@/Components/Forms/NumberInput.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import FileUploadInput, { type InitialFile } from '@/Components/Forms/FileUploadInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import type { Breadcrumb } from '@/types';

interface OptionItem {
    id: string;
    name: string;
}

const { t } = useI18n();

const props = defineProps<{
    item?: App.Data.ItemListItemData;
    roomOptions: OptionItem[];
    userOptions: OptionItem[];
    statusOptions: OptionItem[];
    priorityOptions: OptionItem[];
}>();

const isEditing = computed(() => !!props.item);

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('items'), url: '/items' },
    { label: isEditing.value ? t('edit') : t('create') },
];

const roomSelectOptions = computed(() => [
    { value: '', label: t('whole_house') },
    ...props.roomOptions.map((room) => ({ value: room.id, label: room.name })),
]);

const userSelectOptions = computed(() => [
    { value: '', label: t('unassigned') },
    ...props.userOptions.map((user) => ({ value: user.id, label: user.name })),
]);

const statusSelectOptions = computed(() =>
    props.statusOptions.map((status) => ({ value: status.id, label: status.name })),
);

const prioritySelectOptions = computed(() =>
    props.priorityOptions.map((priority) => ({ value: priority.id, label: priority.name })),
);

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
}

const form = useForm<ItemFormData>(
    isEditing.value ? 'put' : 'post',
    isEditing.value ? `/items/${props.item!.id}` : '/items',
    {
        name: props.item?.name ?? '',
        note: props.item?.note ?? '',
        room_id: props.item?.room_id ?? null,
        unit_price: props.item?.unit_price ?? null,
        quantity: props.item?.quantity ?? 1,
        url: props.item?.url ?? '',
        assigned_user_id: props.item?.assigned_user_id ?? null,
        status: props.item?.status ?? 'planned',
        priority: props.item?.priority ?? 'medium',
        photo_uuid: null,
        remove_photo: false,
    },
);

const photoUuid = ref<string | null>(null);

const initialPhoto = computed<InitialFile[]>(() => {
    if (!props.item?.photo_url) return [];
    return [
        {
            uuid: props.item.photo_uuid ?? '',
            name: t('photo'),
            url: props.item.photo_url,
        },
    ];
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

function onAssignedUserChange(value: string) {
    form.assigned_user_id = value === '' ? null : value;
}

function submit() {
    form.submit();
}
</script>

<template>
    <AppLayout>
        <Header
            :title="
                isEditing
                    ? `${t('edit')} ${t('items').toLowerCase()}`
                    : `${t('create')} ${t('items').toLowerCase()}`
            "
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <a
                    href="/items"
                    class="btn btn-ghost btn-sm"
                >{{ t('cancel') }}</a>
            </template>
        </Header>

        <FormProvider :form="form">
            <form
                novalidate
                @submit.prevent="submit"
            >
                <div class="card bg-base-100 shadow-sm mb-6">
                    <div class="card-body">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <TextInput
                                field="name"
                                :label="t('name')"
                                required
                            />

                            <TextInput
                                field="url"
                                type="url"
                                :label="t('url')"
                            />

                            <SelectInput
                                :model-value="form.room_id ?? ''"
                                :label="t('room')"
                                :options="roomSelectOptions"
                                :error="form.errors.room_id"
                                @update:model-value="onRoomChange"
                            />

                            <SelectInput
                                :model-value="form.assigned_user_id ?? ''"
                                :label="t('assigned_to')"
                                :options="userSelectOptions"
                                :error="form.errors.assigned_user_id"
                                @update:model-value="onAssignedUserChange"
                            />

                            <NumberInput
                                v-model="form.unit_price"
                                :label="t('unit_price')"
                                :min="0"
                                :step="0.01"
                                :error="form.errors.unit_price"
                            />

                            <NumberInput
                                v-model="form.quantity"
                                :label="t('quantity')"
                                :min="1"
                                :max="9999"
                                :step="1"
                                required
                                :error="form.errors.quantity"
                            />

                            <SelectInput
                                field="status"
                                :label="t('status')"
                                :options="statusSelectOptions"
                                required
                            />

                            <SelectInput
                                field="priority"
                                :label="t('priority')"
                                :options="prioritySelectOptions"
                                required
                            />

                            <div class="md:col-span-2">
                                <TextareaInput
                                    v-model="form.note"
                                    :label="t('note')"
                                    :rows="3"
                                    :error="form.errors.note"
                                />
                            </div>

                            <div class="md:col-span-2">
                                <FileUploadInput
                                    :model-value="photoUuid"
                                    :initial-files="initialPhoto"
                                    accept="image/jpeg,image/png,image/webp,image/heic"
                                    :error="form.errors.photo_uuid"
                                    @update:model-value="onPhotoUpdate"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <FormActions
                    cancel-href="/items"
                    :cancel-label="t('cancel')"
                    :submit-label="t('save')"
                    :processing="form.processing"
                />
            </form>
        </FormProvider>
    </AppLayout>
</template>

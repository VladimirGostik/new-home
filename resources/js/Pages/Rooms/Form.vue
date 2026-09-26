<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import NumberInput from '@/Components/Forms/NumberInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import type { Breadcrumb } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    room?: App.Data.RoomListItemData;
}>();

const isEditing = computed(() => !!props.room);

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('rooms'), url: '/rooms' },
    { label: isEditing.value ? t('edit') : t('create') },
];

const form = useForm(
    isEditing.value ? 'put' : 'post',
    isEditing.value ? `/rooms/${props.room!.id}` : '/rooms',
    {
        name: props.room?.name ?? '',
        description: props.room?.description ?? '',
        sort_order: props.room?.sort_order ?? 0,
    },
);

function submit() {
    form.submit();
}
</script>

<template>
    <AppLayout>
        <Header
            :title="
                isEditing
                    ? `${t('edit')} ${t('rooms').toLowerCase()}`
                    : `${t('create')} ${t('rooms').toLowerCase()}`
            "
            :breadcrumbs="breadcrumbs"
        >
            <template #actions>
                <a
                    href="/rooms"
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

                            <NumberInput
                                v-model="form.sort_order"
                                :label="t('sort_order')"
                                :min="0"
                                :step="1"
                                :error="form.errors.sort_order"
                            />

                            <div class="md:col-span-2">
                                <TextareaInput
                                    v-model="form.description"
                                    :label="t('description')"
                                    :rows="4"
                                    :error="form.errors.description"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <FormActions
                    cancel-href="/rooms"
                    :cancel-label="t('cancel')"
                    :submit-label="t('save')"
                    :processing="form.processing"
                />
            </form>
        </FormProvider>
    </AppLayout>
</template>

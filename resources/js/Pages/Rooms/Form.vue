<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ChevronLeftIcon } from '@heroicons/vue/24/outline';
import TextInput from '@/Components/Forms/TextInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';

const props = defineProps<{
    room?: App.Data.RoomListItemData;
}>();

const { t } = useI18n();

const isEditing = computed(() => !!props.room);
const backHref = computed(() => (props.room ? `/rooms/${props.room.id}` : '/rooms'));

// sort_order is omitted: new rooms are appended, order is changed on the rooms list.
const form = useForm(isEditing.value ? 'put' : 'post', isEditing.value ? `/rooms/${props.room!.id}` : '/rooms', {
    name: props.room?.name ?? '',
    description: props.room?.description ?? '',
});

function submit() {
    form.submit();
}
</script>

<template>
    <Head :title="isEditing ? t('edit_room') : t('new_room')" />

    <FormProvider :form="form">
        <form
            novalidate
            class="mx-auto flex max-w-2xl flex-col gap-4.5"
            @submit.prevent="submit"
        >
            <div class="-ml-2 flex items-center gap-2">
                <Link
                    :href="backHref"
                    :aria-label="t('back')"
                    class="flex size-11 items-center justify-center rounded-xl hover:bg-white"
                >
                    <ChevronLeftIcon class="size-5.5 stroke-2" />
                </Link>
                <h1 class="font-display text-[22px] font-semibold lg:text-3xl">{{ isEditing ? t('edit_room') : t('new_room') }}</h1>
            </div>

            <TextInput
                field="name"
                :label="t('item_name')"
                required
                autocomplete="off"
                :placeholder="t('room_name_placeholder')"
            />

            <TextareaInput
                v-model="form.description"
                :label="t('description')"
                :rows="3"
                :error="form.errors.description"
            />

            <div class="flex gap-3">
                <Link
                    :href="backHref"
                    class="btn h-13.5 rounded-2xl border-line bg-white px-6"
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
                    {{ isEditing ? t('save_changes') : t('add_room') }}
                </button>
            </div>
        </form>
    </FormProvider>
</template>

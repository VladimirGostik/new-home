<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';
import PasswordInput from '@/Components/Forms/PasswordInput.vue';
import CheckboxInput from '@/Components/Forms/CheckboxInput.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import BrandMark from '@/Components/BrandMark.vue';

const appName = (import.meta.env.VITE_APP_NAME as string | undefined) ?? 'App';

const { t } = useI18n();

const { canResetPassword } = defineProps<{ canResetPassword: boolean }>();

const form = useForm('post', '/login', {
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.submit({
        onFinish: () => {
            form.reset('password');
        },
    });
}
</script>

<template>
    <div class="flex min-h-dvh flex-col items-center justify-center gap-8 bg-paper p-4">
        <div class="flex items-center gap-3">
            <BrandMark class="size-11 rounded-xl" />
            <span class="font-display text-3xl font-semibold tracking-tight">{{ appName }}</span>
        </div>
        <div class="card w-full max-w-md rounded-[22px] border border-line bg-white">
            <div class="card-body gap-0 p-6">
                <h1 class="mb-4 font-display text-2xl font-semibold">
                    {{ t('login') }}
                </h1>

                <FormProvider :form="form">
                    <form
                        novalidate
                        @submit.prevent="submit"
                    >
                        <TextInput
                            field="email"
                            :label="t('email')"
                            type="email"
                            autocomplete="email"
                            required
                        />

                        <PasswordInput
                            field="password"
                            :label="t('password')"
                            autocomplete="current-password"
                            required
                            class="mt-2"
                        />

                        <div class="mt-4">
                            <CheckboxInput
                                field="remember"
                                :label="t('remember_me')"
                            />
                        </div>

                        <div class="flex items-center justify-between mt-6">
                            <a
                                v-if="canResetPassword"
                                href="/forgot-password"
                                class="link link-primary text-sm"
                            >
                                {{ t('forgot_password') }}
                            </a>
                            <button
                                type="submit"
                                class="btn btn-primary h-12 rounded-[14px] px-6"
                                :disabled="form.processing"
                            >
                                <span
                                    v-if="form.processing"
                                    class="loading loading-spinner loading-xs"
                                />
                                {{ t('login') }}
                            </button>
                        </div>
                    </form>
                </FormProvider>
            </div>
        </div>
    </div>
</template>

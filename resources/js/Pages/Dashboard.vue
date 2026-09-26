<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { TagIcon, UserIcon, PlusIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import SpendHero from '@/Components/SpendHero.vue';
import ProgressBar from '@/Components/ProgressBar.vue';
import { boughtPercent, formatEur } from '@/Composables/useMoney';

const props = defineProps<{
    dashboard: App.Data.DashboardData;
}>();

const { t } = useI18n();
const page = usePage();

const firstName = computed(() => (page.props.auth.user?.name ?? '').split(' ')[0]);
const totals = computed(() => props.dashboard.totals);
const isEmpty = computed(() => totals.value.items_count === 0);

function roomHref(group: App.Data.SpendGroupData): string {
    return group.id ? `/rooms/${group.id}` : '/items?filter[room]=house';
}

function personHref(group: App.Data.SpendGroupData): string {
    return `/items?filter[assignee]=${group.id ?? 'none'}`;
}

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

const AVATAR_TONES = ['bg-olive text-white', 'bg-oak text-ink', 'bg-[#3e4a1b] text-white', 'bg-oak-deep text-white'];
function avatarClass(group: App.Data.SpendGroupData, index: number): string {
    return group.id ? AVATAR_TONES[index % AVATAR_TONES.length] : 'bg-line-soft text-muted';
}
</script>

<template>
    <Head :title="t('dashboard')" />

    <AppLayout>
        <div class="flex flex-col gap-6 lg:gap-7">
            <div class="flex items-end justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <span class="text-sm text-muted">{{ t('hello_name', { name: firstName }) }}</span>
                    <h1 class="font-display text-[28px] leading-tight font-semibold tracking-tight lg:text-4xl">{{ t('dashboard_title') }}</h1>
                </div>
                <Link
                    href="/items/create"
                    class="btn btn-primary hidden h-11.5 rounded-[14px] px-5 lg:inline-flex"
                >
                    <PlusIcon class="size-4.5 stroke-2" />
                    {{ t('new_item') }}
                </Link>
            </div>

            <div
                v-if="isEmpty"
                class="flex flex-col items-start gap-3 rounded-[20px] border border-dashed border-[#d6d0c2] bg-white p-6"
            >
                <h2 class="font-display text-xl font-semibold">{{ t('empty_plan_title') }}</h2>
                <p class="text-muted">{{ t('empty_plan_text') }}</p>
                <Link
                    href="/items/create"
                    class="btn btn-primary rounded-[14px]"
                >{{ t('add_first_item') }}</Link>
            </div>

            <template v-else>
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] lg:items-start">
                    <SpendHero :summary="totals" />

                    <div class="grid grid-cols-2 gap-3 lg:h-full lg:grid-cols-1">
                        <Link
                            :href="totals.unpriced_count > 0 ? '/items?filter[price]=none' : '/items'"
                            class="flex items-center gap-2.5 rounded-2xl bg-oak-soft p-3.5 text-oak-ink transition-opacity hover:opacity-85 lg:p-5"
                        >
                            <TagIcon class="size-5 shrink-0" />
                            <span class="flex flex-col">
                                <strong class="text-[17px] tabular-nums">{{ totals.unpriced_count }}</strong>
                                <span class="text-[13px]">{{ t('without_price') }}</span>
                            </span>
                        </Link>
                        <Link
                            :href="dashboard.unassigned_count > 0 ? '/items?filter[assignee]=none' : '/items'"
                            class="flex items-center gap-2.5 rounded-2xl bg-olive-soft p-3.5 text-olive-deep transition-opacity hover:opacity-85 lg:p-5"
                        >
                            <UserIcon class="size-5 shrink-0" />
                            <span class="flex flex-col">
                                <strong class="text-[17px] tabular-nums">{{ dashboard.unassigned_count }}</strong>
                                <span class="text-[13px]">{{ t('without_person') }}</span>
                            </span>
                        </Link>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] lg:items-start">
                    <section
                        aria-labelledby="rooms-heading"
                        class="flex flex-col gap-3"
                    >
                        <div class="flex items-baseline justify-between">
                            <h2
                                id="rooms-heading"
                                class="font-display text-[22px] font-semibold"
                            >{{ t('rooms') }}</h2>
                            <Link
                                href="/rooms"
                                class="text-sm font-medium text-olive hover:text-olive-deep"
                            >{{ t('all') }}</Link>
                        </div>
                        <div class="flex flex-col overflow-hidden rounded-[18px] border border-line bg-white">
                            <Link
                                v-for="group in dashboard.rooms"
                                :key="group.id ?? 'house'"
                                :href="roomHref(group)"
                                class="flex flex-col gap-2 border-b border-line-soft px-4 py-3.5 transition-colors last:border-b-0 hover:bg-paper"
                            >
                                <span class="flex items-baseline justify-between gap-3">
                                    <span class="text-base font-medium">{{ group.name }}</span>
                                    <span class="text-base font-semibold tabular-nums">{{ formatEur(group.summary.total) }}</span>
                                </span>
                                <span class="flex items-center gap-2.5">
                                    <ProgressBar
                                        class="flex-1"
                                        :value="boughtPercent(group.summary)"
                                        :label="t('bought_percent', { n: boughtPercent(group.summary) })"
                                    />
                                    <span class="min-w-22 text-right text-xs text-muted">{{ t('items_short_count', group.summary.items_count) }} · {{ boughtPercent(group.summary) }} %</span>
                                </span>
                            </Link>
                        </div>
                    </section>

                    <section
                        aria-labelledby="people-heading"
                        class="flex flex-col gap-3"
                    >
                        <h2
                            id="people-heading"
                            class="font-display text-[22px] font-semibold"
                        >{{ t('who_buys_what') }}</h2>
                        <p
                            v-if="dashboard.people.length === 0"
                            class="rounded-2xl border border-line bg-white p-4 text-sm text-muted"
                        >{{ t('nobody_assigned_yet') }}</p>
                        <div class="flex flex-col gap-2.5">
                            <Link
                                v-for="(group, index) in dashboard.people"
                                :key="group.id ?? 'none'"
                                :href="personHref(group)"
                                class="flex items-center gap-3 rounded-2xl border border-line bg-white p-3.5 transition-colors hover:bg-paper"
                            >
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                                    :class="avatarClass(group, index)"
                                >{{ group.id ? initials(group.name) : '?' }}</span>
                                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span class="truncate text-base font-medium">{{ group.name }}</span>
                                    <span class="text-[13px] text-muted">{{ t('items_count', group.summary.items_count) }}</span>
                                </span>
                                <span class="flex flex-col items-end gap-0.5">
                                    <span class="text-[15px] font-semibold tabular-nums">{{ t('amount_remaining', { amount: formatEur(group.summary.remaining_total) }) }}</span>
                                    <span class="text-xs text-olive tabular-nums">{{ t('amount_bought', { amount: formatEur(group.summary.bought_total) }) }}</span>
                                </span>
                            </Link>
                        </div>
                    </section>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

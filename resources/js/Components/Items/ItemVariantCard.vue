<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { PhotoIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { HandThumbUpIcon } from '@heroicons/vue/24/solid';
import { formatEur } from '@/Composables/useMoney';

defineProps<{
    variant: App.Data.ItemVariantListItemData;
    quantity: number;
    isLeader: boolean;
    isTie: boolean;
    votePending: boolean;
    selectBusy: boolean;
    canEdit: boolean;
    canDelete: boolean;
}>();

const emit = defineEmits<{
    vote: [];
    select: [];
    unselect: [];
    edit: [];
    delete: [];
}>();

const { t } = useI18n();

function round2(value: number): number {
    return Math.round(value * 100) / 100;
}

function hostname(url: string): string {
    try {
        return new URL(url).hostname;
    } catch {
        return url;
    }
}
</script>

<template>
    <article
        class="flex flex-col gap-3 rounded-[18px] border bg-white p-3"
        :class="variant.is_selected ? 'border-olive ring-1 ring-olive' : 'border-line'"
    >
        <div class="flex items-start gap-3">
            <a
                v-if="variant.photo_thumb_url"
                :href="variant.photo_url ?? undefined"
                target="_blank"
                rel="noopener noreferrer"
                class="flex size-16 shrink-0 overflow-hidden rounded-xl bg-oak-soft text-oak-deep"
            >
                <img
                    :src="variant.photo_thumb_url"
                    :alt="variant.name"
                    loading="lazy"
                    class="size-full object-cover"
                />
            </a>
            <span
                v-else
                class="flex size-16 shrink-0 items-center justify-center rounded-xl bg-oak-soft text-oak-deep"
            >
                <PhotoIcon class="size-6" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="font-semibold break-words">{{ variant.name }}</p>
                <p class="text-[15px] font-semibold tabular-nums">{{ variant.unit_price === null ? t('no_price') : formatEur(variant.unit_price) }}</p>
                <p
                    v-if="quantity > 1 && variant.unit_price !== null"
                    class="text-[13px] text-muted"
                >{{ t('variant_total_for_qty', { qty: quantity, total: formatEur(round2(variant.unit_price * quantity)) }) }}</p>
                <a
                    v-if="variant.url"
                    :href="variant.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-11 items-center text-sm text-muted underline"
                >{{ hostname(variant.url) }}</a>
            </div>

            <div class="flex shrink-0 gap-1">
                <button
                    v-if="canEdit"
                    type="button"
                    :aria-label="t('edit_variant_named', { name: variant.name })"
                    class="flex size-11 items-center justify-center rounded-xl text-muted hover:bg-line-soft"
                    @click="emit('edit')"
                >
                    <PencilSquareIcon class="size-5" />
                </button>
                <button
                    v-if="canDelete"
                    type="button"
                    :aria-label="t('delete_variant_named', { name: variant.name })"
                    class="flex size-11 items-center justify-center rounded-xl text-error hover:bg-line-soft"
                    @click="emit('delete')"
                >
                    <TrashIcon class="size-5" />
                </button>
            </div>
        </div>

        <div
            v-if="variant.is_selected || isLeader"
            class="flex flex-wrap gap-1.5"
        >
            <span
                v-if="variant.is_selected"
                class="inline-flex h-5.5 items-center rounded-full bg-olive px-2 text-xs text-white"
            >{{ t('variant_selected_badge') }}</span>
            <span
                v-if="isLeader && !isTie"
                class="inline-flex h-5.5 items-center rounded-full bg-oak-soft px-2 text-xs text-oak-ink"
            >{{ t('variant_leading') }}</span>
            <span
                v-if="isLeader && isTie"
                class="inline-flex h-5.5 items-center rounded-full bg-oak-soft px-2 text-xs text-oak-ink"
            >{{ t('variant_tied') }}</span>
        </div>

        <p class="text-[13px] text-muted">
            {{ t('votes_count', variant.vote_count) }}<template v-if="variant.voter_names.length">: {{ variant.voter_names.join(', ') }}</template>
        </p>

        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                :aria-pressed="variant.is_my_vote"
                :aria-label="variant.is_my_vote ? t('retract_vote_named', { name: variant.name }) : t('vote_for_named', { name: variant.name })"
                :disabled="votePending"
                class="inline-flex h-11 items-center gap-1.5 rounded-xl px-4 text-sm font-medium"
                :class="variant.is_my_vote ? 'bg-olive text-white' : 'border border-line bg-white'"
                @click="emit('vote')"
            >
                <HandThumbUpIcon class="size-4.5" />
                {{ variant.is_my_vote ? t('my_vote') : t('vote') }}
            </button>

            <button
                v-if="!variant.is_selected && canEdit"
                type="button"
                :disabled="selectBusy"
                class="btn btn-outline btn-primary h-11 rounded-xl"
                @click="emit('select')"
            >
                <span
                    v-if="selectBusy"
                    class="loading loading-spinner loading-xs"
                />
                {{ t('select_this_variant') }}
            </button>
            <button
                v-else-if="variant.is_selected && canEdit"
                type="button"
                class="btn btn-ghost h-11 rounded-xl"
                @click="emit('unselect')"
            >
                {{ t('clear_selection') }}
            </button>
        </div>
    </article>
</template>

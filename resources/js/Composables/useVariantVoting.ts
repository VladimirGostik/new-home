import { computed, ref, toValue, type ComputedRef, type MaybeRefOrGetter } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useToast } from '@/Composables/useToast';

type Variant = App.Data.ItemVariantListItemData;

const voterNameCollator = new Intl.Collator('sk');

export function useVariantVoting(itemId: MaybeRefOrGetter<string>, variants: MaybeRefOrGetter<Variant[]>) {
    const { t } = useI18n();
    const page = usePage();

    // undefined = nothing pending, null = retracting, id = voting for that variant.
    const pendingVoteId = ref<string | null | undefined>(undefined);

    const pending = computed(() => pendingVoteId.value !== undefined);

    const displayVariants: ComputedRef<Variant[]> = computed(() => {
        const list = toValue(variants);
        if (pendingVoteId.value === undefined) return list;

        const myName = page.props.auth.user?.name;

        return list.map((variant) => {
            const was = variant.is_my_vote;
            const now = variant.id === pendingVoteId.value;
            if (was === now) return variant;

            const delta = now ? 1 : -1;
            let voterNames = variant.voter_names;
            if (myName) {
                if (now) {
                    const insertAt = voterNames.findIndex((name) => voterNameCollator.compare(name, myName) > 0);
                    voterNames =
                        insertAt === -1
                            ? [...voterNames, myName]
                            : [...voterNames.slice(0, insertAt), myName, ...voterNames.slice(insertAt)];
                } else {
                    voterNames = voterNames.filter((name) => name !== myName);
                }
            }

            return {
                ...variant,
                vote_count: variant.vote_count + delta,
                is_my_vote: now,
                voter_names: voterNames,
            };
        });
    });

    const leaderIds = computed<Set<string>>(() => {
        const list = displayVariants.value;
        if (list.length < 2) return new Set();
        const max = Math.max(0, ...list.map((variant) => variant.vote_count));
        if (max === 0) return new Set();
        return new Set(list.filter((variant) => variant.vote_count === max).map((variant) => variant.id));
    });

    const isTie = computed(() => leaderIds.value.size > 1);

    function toastFail(): void {
        useToast().error(t('vote_failed'));
    }

    function vote(variantId: string): void {
        if (pending.value) return;

        const current = toValue(variants).find((variant) => variant.id === variantId);
        if (current?.is_my_vote) {
            retract();
            return;
        }

        pendingVoteId.value = variantId;
        router.post(
            `/items/${toValue(itemId)}/variants/${variantId}/vote`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onError: toastFail,
                onHttpException: toastFail,
                onNetworkError: toastFail,
                onFinish: () => (pendingVoteId.value = undefined),
            },
        );
    }

    function retract(): void {
        pendingVoteId.value = null;
        router.delete(`/items/${toValue(itemId)}/vote`, {
            preserveScroll: true,
            preserveState: true,
            onError: toastFail,
            onHttpException: toastFail,
            onNetworkError: toastFail,
            onFinish: () => (pendingVoteId.value = undefined),
        });
    }

    return { displayVariants, leaderIds, isTie, pending, vote, retract };
}

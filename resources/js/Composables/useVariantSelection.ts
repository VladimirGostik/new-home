import { ref, toValue, type MaybeRefOrGetter } from 'vue';
import { router } from '@inertiajs/vue3';

export function useVariantSelection(itemId: MaybeRefOrGetter<string>) {
    const busyId = ref<string | null>(null);

    function select(variantId: string): void {
        busyId.value = variantId;
        router.post(
            `/items/${toValue(itemId)}/variants/${variantId}/select`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => (busyId.value = null),
            },
        );
    }

    function unselect(): void {
        busyId.value = 'unselect';
        router.delete(`/items/${toValue(itemId)}/selected-variant`, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (busyId.value = null),
        });
    }

    return { busyId, select, unselect };
}

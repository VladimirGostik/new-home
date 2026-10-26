import { router } from '@inertiajs/vue3';

type ExpenseQueryNext = Partial<{ month: string; search: string; type: string }>;

export function useExpenseQuery(filters: () => App.Data.ExpenseIndexFilterData) {
    function visit(next: ExpenseQueryNext) {
        const current = filters();
        const merged = {
            month: next.month ?? current.month,
            search: next.search ?? current.search ?? '',
            type: next.type ?? current.type ?? '',
        };

        const filter = Object.fromEntries(
            Object.entries({ search: merged.search, type: merged.type }).filter(([, value]) => value !== ''),
        );

        router.get(
            '/expenses',
            { month: merged.month, ...(Object.keys(filter).length ? { filter } : {}) },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return { visit };
}

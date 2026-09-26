<script setup lang="ts">
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';

import { formatDatetime } from '@/utils/date';
import type { Breadcrumb, Paginator, TableColumn } from '@/types';
import type { FilterConfig } from '@/types/table';

interface OptionItem {
    id: string;
    name: string;
}

interface ItemFilterOptions {
    rooms: OptionItem[];
    users: OptionItem[];
    statuses: OptionItem[];
    priorities: OptionItem[];
}

const { t } = useI18n();
const page = usePage();

const props = defineProps<{
    items: Paginator<App.Data.ItemListItemData>;
    filters?: Record<string, unknown>;
    filterOptions: ItemFilterOptions;
}>();

const can = page.props.can as Record<string, boolean>;

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('items') },
];

const columns: TableColumn[] = [
    { key: 'name', label: t('name'), sortable: true },
    { key: 'room_name', label: t('room'), sortable: false },
    { key: 'assigned_user_name', label: t('assigned_to'), sortable: false },
    { key: 'unit_price', label: t('unit_price'), sortable: true },
    { key: 'total_price', label: t('total_price'), sortable: false },
    { key: 'priority', label: t('priority'), sortable: true },
    { key: 'status', label: t('status'), sortable: true },
    { key: 'created_at', label: t('created_at'), sortable: true },
];

const filterDefinitions = computed<FilterConfig[]>(() => [
    {
        property: 'search',
        label: t('search'),
        type: 'text',
        placeholder: t('search'),
        defaultOperator: '~',
    },
    {
        property: 'room',
        label: t('room'),
        type: 'select',
        options: [
            { value: 'house', label: t('whole_house') },
            ...props.filterOptions.rooms.map((room) => ({ value: room.id, label: room.name })),
        ],
    },
    {
        property: 'assignee',
        label: t('assigned_to'),
        type: 'select',
        options: [
            { value: 'me', label: t('assigned_to_me') },
            { value: 'none', label: t('unassigned') },
            ...props.filterOptions.users.map((user) => ({ value: user.id, label: user.name })),
        ],
    },
    {
        property: 'status',
        label: t('status'),
        type: 'select',
        options: props.filterOptions.statuses.map((status) => ({ value: status.id, label: status.name })),
    },
    {
        property: 'priority',
        label: t('priority'),
        type: 'select',
        options: props.filterOptions.priorities.map((priority) => ({ value: priority.id, label: priority.name })),
    },
]);

function priorityBadgeClass(value: string): string {
    return (
        {
            low: 'badge-ghost',
            medium: 'badge-info',
            high: 'badge-error',
        } as Record<string, string>
    )[value] ?? 'badge-ghost';
}

function statusBadgeClass(value: string): string {
    return value === 'bought' ? 'badge-success' : 'badge-warning';
}

function toggleStatus(item: App.Data.ItemListItemData) {
    router.patch(`/items/${item.id}/status`, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Header :title="t('items')" :breadcrumbs="breadcrumbs">
            <template #actions>
                <a
                    v-if="can.createItems"
                    href="/items/create"
                    class="btn btn-primary btn-sm"
                >
                    {{ t('create') }}
                </a>
            </template>
        </Header>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <DataTable
                    :columns="columns"
                    :rows="items"
                    :filters="filterDefinitions"
                    route-name="items.index"
                    :can-edit="!!can.editItems"
                    :can-delete="!!can.deleteItems"
                    :edit-url="(row: App.Data.ItemListItemData) => `/items/${row.id}/edit`"
                    :delete-url="(row: App.Data.ItemListItemData) => `/items/${row.id}`"
                >
                    <template #cell-room_name="{ value }">
                        <span class="text-sm text-base-content/70">{{ value ?? t('whole_house') }}</span>
                    </template>

                    <template #cell-assigned_user_name="{ value }">
                        <span class="text-sm text-base-content/70">{{ value ?? t('unassigned') }}</span>
                    </template>

                    <template #cell-unit_price="{ value }">
                        <span>{{ value !== null ? value : '—' }}</span>
                    </template>

                    <template #cell-total_price="{ value }">
                        <span>{{ value !== null ? value : '—' }}</span>
                    </template>

                    <template #cell-priority="{ value }">
                        <span :class="`badge ${priorityBadgeClass(value as string)}`">
                            {{ t(`priority_${value}`) }}
                        </span>
                    </template>

                    <template #cell-status="{ row, value }">
                        <button
                            type="button"
                            class="badge cursor-pointer"
                            :class="statusBadgeClass(value as string)"
                            :disabled="!can.editItems"
                            @click="toggleStatus(row as App.Data.ItemListItemData)"
                        >
                            {{ t(`status_${value}`) }}
                        </button>
                    </template>

                    <template #cell-created_at="{ value }">
                        <span class="text-sm text-base-content/70">
                            {{ formatDatetime(value as string | null) }}
                        </span>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>

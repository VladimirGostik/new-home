<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';

import { formatDatetime } from '@/utils/date';
import type { Breadcrumb, Paginator, TableColumn } from '@/types';
import type { FilterConfig } from '@/types/table';

const { t } = useI18n();
const page = usePage();

defineProps<{
    rooms: Paginator<App.Data.RoomListItemData>;
    filters?: Record<string, unknown>;
}>();

const can = page.props.can as Record<string, boolean>;

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('rooms') },
];

const columns: TableColumn[] = [
    { key: 'name', label: t('name'), sortable: true },
    { key: 'description', label: t('description'), sortable: false },
    { key: 'sort_order', label: t('sort_order'), sortable: true },
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
        property: 'name',
        label: t('name'),
        type: 'text',
        placeholder: t('name'),
        defaultOperator: '~',
    },
    {
        property: 'sort_order',
        label: t('sort_order'),
        type: 'number',
        placeholder: t('sort_order'),
        defaultOperator: '=',
        operators: ['=', '!=', '<', '<=', '>', '>=', 'between'],
    },
    {
        property: 'created_at',
        label: t('created_at'),
        type: 'date',
        placeholder: t('created_at'),
        defaultOperator: '>=',
        operators: ['>=', '<=', 'between'],
    },
]);
</script>

<template>
    <AppLayout>
        <Header :title="t('rooms')" :breadcrumbs="breadcrumbs">
            <template #actions>
                <a
                    v-if="can.createRooms"
                    href="/rooms/create"
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
                    :rows="rooms"
                    :filters="filterDefinitions"
                    route-name="rooms.index"
                    :can-edit="!!can.editRooms"
                    :can-delete="!!can.deleteRooms"
                    :edit-url="(row: App.Data.RoomListItemData) => `/rooms/${row.id}/edit`"
                    :delete-url="(row: App.Data.RoomListItemData) => `/rooms/${row.id}`"
                >
                    <template #cell-description="{ value }">
                        <span class="text-sm text-base-content/70 line-clamp-1">
                            {{ value ?? '—' }}
                        </span>
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

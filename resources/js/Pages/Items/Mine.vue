<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps<{
    items: App.Data.ItemListItemData[];
    summary: App.Data.SpendSummaryData;
}>();
</script>

<template>
    <AppLayout>
        <!-- Placeholder: full mobile-first page is built in the frontend step. -->
        <ul>
            <li v-for="item in items" :key="item.id">{{ item.name }}</li>
        </ul>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps<{
    room: App.Data.RoomListItemData;
    items: App.Data.ItemListItemData[];
}>();
</script>

<template>
    <AppLayout>
        <!-- Placeholder: full mobile-first page is built in the frontend step. -->
        <h1>{{ room.name }}</h1>
        <ul>
            <li v-for="item in items" :key="item.id">{{ item.name }}</li>
        </ul>
    </AppLayout>
</template>

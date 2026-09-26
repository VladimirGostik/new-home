<script setup lang="ts">
defineProps<{
    item: App.Data.ItemListItemData;
    variants: App.Data.ItemVariantListItemData[];
}>();
</script>

<template>
    <div>{{ item.name }}</div>
</template>

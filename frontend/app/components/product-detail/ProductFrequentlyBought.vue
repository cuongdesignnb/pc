<script setup lang="ts">
defineProps<{ slug: string }>()
</script>

<template><ProductRelationList :slug="slug" type="frequently_bought" title="Mua kèm ưu đãi" mode="compact-selectable" :limit="6" /></template>

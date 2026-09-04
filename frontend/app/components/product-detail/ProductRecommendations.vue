<script setup lang="ts">
defineProps<{ slug: string }>()
</script>

<template><ProductRelationList :slug="slug" type="related" title="Sản phẩm liên quan" mode="horizontal-carousel" :limit="8" /></template>

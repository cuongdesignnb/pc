<script setup lang="ts">
defineProps<{ slug: string }>()
</script>

<template><ProductRelationList :slug="slug" type="frequently_bought" title="Thường mua cùng" description="Các sản phẩm được quản trị viên gợi ý mua kèm." /></template>

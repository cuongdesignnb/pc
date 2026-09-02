<script setup lang="ts">
import type { ProductDetail } from '~/types/product-detail'

defineProps<{ product: ProductDetail }>()
</script>

<template>
  <nav aria-label="Điều hướng sản phẩm" class="flex items-center gap-2 overflow-hidden py-3 text-sm text-slate-500">
    <NuxtLink to="/" class="shrink-0 hover:text-blue-700">Trang chủ</NuxtLink>
    <span aria-hidden="true">/</span>
    <NuxtLink v-if="product.category" :to="`/${product.category.slug}`" class="truncate hover:text-blue-700">{{ product.category.name }}</NuxtLink>
    <template v-if="product.category"><span aria-hidden="true">/</span></template>
    <span class="truncate font-medium text-slate-700">{{ product.name }}</span>
  </nav>
</template>

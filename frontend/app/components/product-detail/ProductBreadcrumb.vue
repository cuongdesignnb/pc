<script setup lang="ts">
import type { ProductDetail } from '~/types/product-detail'

defineProps<{ product: ProductDetail }>()
</script>

<template>
  <nav aria-label="Điều hướng sản phẩm" class="flex min-h-[38px] items-center gap-2 overflow-hidden border-b border-slate-100 text-[11px] text-slate-500">
    <NuxtLink to="/" class="shrink-0 hover:text-blue-700">Trang chủ</NuxtLink>
    <span aria-hidden="true" class="text-slate-300">›</span>
    <NuxtLink to="/linh-kien-pc" class="shrink-0 hover:text-blue-700">Linh kiện</NuxtLink>
    <template v-if="product.category">
      <span aria-hidden="true" class="text-slate-300">›</span>
      <NuxtLink :to="`/${product.category.slug}`" class="truncate hover:text-blue-700">{{ product.category.name }}</NuxtLink>
    </template>
    <template v-if="product.component_type">
      <span aria-hidden="true" class="text-slate-300">›</span>
      <span class="shrink-0 truncate">{{ product.component_type.name }}</span>
    </template>
    <span aria-hidden="true" class="text-slate-300">›</span>
    <span class="truncate font-semibold text-slate-700">{{ product.name }}</span>
  </nav>
</template>

<script setup lang="ts">
import type { ProductDetail } from '~/types/product-detail'

defineProps<{ product: ProductDetail; sku: string }>()
const emit = defineEmits<{ reviews: [] }>()
</script>

<template>
  <div>
    <span v-if="product.brand" class="text-xs font-bold uppercase tracking-wide text-blue-700">{{ product.brand.name }}</span>
    <h1 class="mt-2 text-2xl font-bold leading-tight text-slate-900 lg:text-3xl">{{ product.name }}</h1>
    <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
      <button v-if="product.rating.count" type="button" class="inline-flex items-center gap-1 text-amber-600 hover:text-amber-700" @click="emit('reviews')">
        <span aria-hidden="true">★</span><span class="font-semibold">{{ product.rating.average }}</span><span class="text-slate-500">({{ product.rating.count }} đánh giá)</span>
      </button>
      <span v-if="sku" class="text-slate-500">SKU: {{ sku }}</span>
    </div>
  </div>
</template>

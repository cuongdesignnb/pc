<script setup lang="ts">
import type { ProductDetail } from '~/types/product-detail'

defineProps<{ product: ProductDetail; sku: string }>()
const emit = defineEmits<{ reviews: [] }>()
</script>

<template>
  <div class="pdp-title-meta">
    <span v-if="product.is_featured" class="inline-flex rounded bg-blue-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-blue-700">Bán chạy</span>
    <h1 class="mt-2 text-[20px] font-bold leading-[1.25] text-slate-900 lg:text-[22px]">{{ product.name }}</h1>
    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
      <button v-if="product.rating.count" type="button" class="inline-flex items-center gap-1 text-amber-500 hover:text-amber-600" @click="emit('reviews')">
        <span aria-hidden="true">★★★★★</span><span class="font-semibold text-slate-800">{{ product.rating.average ?? '—' }}</span><span class="text-slate-500">({{ product.rating.count }} đánh giá)</span>
      </button>
      <span v-else class="text-slate-500">Chưa có đánh giá</span>
      <span v-if="product.sold_count > 0" class="text-slate-400">|</span>
      <span v-if="product.sold_count > 0" class="text-slate-500">Đã bán {{ product.sold_count.toLocaleString('vi-VN') }}</span>
      <span v-if="sku" class="text-slate-400">|</span>
      <span v-if="sku" class="text-slate-500">SKU: {{ sku }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ProductReviewSummary } from '~/types/product-detail'
defineProps<{ summary: ProductReviewSummary }>()
</script>

<template>
  <section class="rounded-xl bg-amber-50 p-4 sm:flex sm:items-center sm:gap-8">
    <div class="text-center"><strong class="text-4xl text-amber-600">{{ summary.average ?? '—' }}</strong><p class="mt-1 text-sm text-amber-800">{{ summary.count }} đánh giá</p></div>
    <div v-if="summary.count" class="mt-4 flex-1 space-y-1.5 sm:mt-0"><div v-for="star in [5, 4, 3, 2, 1]" :key="star" class="flex items-center gap-2 text-xs"><span class="w-8 text-slate-600">{{ star }} ★</span><span class="h-2 flex-1 overflow-hidden rounded bg-white"><span class="block h-full bg-amber-400" :style="{ width: `${((summary.breakdown[String(star)] || 0) / summary.count) * 100}%` }" /></span><span class="w-7 text-right text-slate-500">{{ summary.breakdown[String(star)] || 0 }}</span></div></div>
  </section>
</template>

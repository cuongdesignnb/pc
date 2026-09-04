<script setup lang="ts">
import type { ProductReviewSummary } from '~/types/product-detail'

defineProps<{ summary: ProductReviewSummary }>()
</script>

<template>
  <section class="pdp-review-summary">
    <div class="text-center sm:text-left">
      <strong class="block text-[32px] leading-none text-slate-900">{{ summary.average ?? '—' }}<span class="text-base font-medium text-slate-500">/5</span></strong>
      <div class="mt-1 text-sm tracking-[0.12em] text-amber-500" aria-label="Xếp hạng sao">★★★★★</div>
      <p class="mt-1 text-[11px] text-slate-500">Dựa trên {{ summary.count }} đánh giá</p>
    </div>
    <div v-if="summary.count" class="mt-4 space-y-1.5 sm:mt-5">
      <div v-for="star in [5, 4, 3, 2, 1]" :key="star" class="flex items-center gap-2 text-[10px]">
        <span class="w-8 shrink-0 text-slate-500">{{ star }} sao</span>
        <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-orange-400" :style="{ width: `${((summary.breakdown[String(star)] || 0) / summary.count) * 100}%` }" /></span>
        <span class="w-6 text-right text-slate-400">{{ summary.breakdown[String(star)] || 0 }}</span>
      </div>
    </div>
  </section>
</template>

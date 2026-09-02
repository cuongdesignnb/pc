<script setup lang="ts">
defineProps<{ pricing: { price: number; sale_price: number | null; display_price: number; discount_percent: number; saving: number } }>()
const { formatMoney } = useSettings()
</script>

<template>
  <div class="rounded-xl bg-blue-50 p-4">
    <div class="flex flex-wrap items-end gap-x-3 gap-y-1">
      <strong class="text-3xl leading-none text-blue-700">{{ formatMoney(pricing.display_price) }}</strong>
      <span v-if="pricing.sale_price !== null" class="text-sm text-slate-400 line-through">{{ formatMoney(pricing.price) }}</span>
      <span v-if="pricing.discount_percent > 0" class="rounded bg-rose-600 px-1.5 py-0.5 text-xs font-bold text-white">-{{ pricing.discount_percent }}%</span>
    </div>
    <p v-if="pricing.saving > 0" class="mt-2 text-sm font-medium text-rose-600">Tiết kiệm {{ formatMoney(pricing.saving) }}</p>
  </div>
</template>

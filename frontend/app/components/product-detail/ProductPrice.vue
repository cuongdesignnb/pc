<script setup lang="ts">
defineProps<{ pricing: { price: number; sale_price: number | null; display_price: number; discount_percent: number; saving: number } }>()
const { formatMoney, getString } = useSettings()
const installmentMessage = computed(() => getString('storefront_installment_message'))
</script>

<template>
  <section class="border-y border-slate-100 py-3">
    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
      <strong class="text-[28px] font-bold leading-none text-red-600">{{ formatMoney(pricing.display_price) }}</strong>
      <span v-if="pricing.sale_price !== null" class="text-xs text-slate-400 line-through">{{ formatMoney(pricing.price) }}</span>
      <span v-if="pricing.discount_percent > 0" class="rounded border border-red-200 px-1.5 py-0.5 text-[10px] font-bold text-red-600">-{{ pricing.discount_percent }}%</span>
    </div>
    <p v-if="pricing.saving > 0" class="mt-1 text-xs font-semibold text-red-600">Tiết kiệm: {{ formatMoney(pricing.saving) }}</p>
    <p v-if="installmentMessage" class="mt-2 inline-flex items-center gap-2 rounded bg-slate-50 px-2.5 py-1.5 text-[11px] text-slate-600">
      <span class="text-blue-600" aria-hidden="true">▣</span>
      {{ installmentMessage }}
    </p>
  </section>
</template>

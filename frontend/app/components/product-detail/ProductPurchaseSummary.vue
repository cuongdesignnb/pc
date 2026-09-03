<script setup lang="ts">
import type { ProductDetail, ProductVariant } from '~/types/product-detail'

const props = defineProps<{ product: ProductDetail; variant: ProductVariant | null; quantity: number; purchasable: boolean }>()
const emit = defineEmits<{ add: []; buy: [] }>()
const { formatMoney, shippingFreeThreshold, shippingDefaultFee } = useSettings()
const unitPrice = computed(() => props.variant?.pricing.display_price ?? props.product.pricing.display_price)
const shippingFee = computed(() => shippingFreeThreshold.value > 0 && unitPrice.value * props.quantity >= shippingFreeThreshold.value ? 0 : shippingDefaultFee.value)
</script>

<template>
  <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:sticky lg:top-24">
    <h2 class="text-base font-bold text-slate-900">Tóm tắt đơn hàng</h2>
    <div class="mt-4 flex gap-3">
      <NuxtImg v-if="product.images[0]?.url" :src="product.images[0].url" :alt="product.images[0].alt || product.name" width="64" height="64" class="h-16 w-16 rounded-lg border border-slate-100 object-contain" />
      <div class="min-w-0"><p class="line-clamp-2 text-sm font-semibold text-slate-800">{{ product.name }}</p><p v-if="variant" class="mt-1 text-xs text-slate-500">{{ variant.name }}</p></div>
    </div>
    <dl class="mt-5 space-y-2 border-y border-slate-100 py-4 text-sm">
      <div class="flex justify-between gap-3"><dt class="text-slate-500">Số lượng</dt><dd class="font-medium">{{ quantity }}</dd></div>
      <div class="flex justify-between gap-3"><dt class="text-slate-500">Tạm tính</dt><dd class="font-medium">{{ formatMoney(unitPrice * quantity) }}</dd></div>
      <div class="flex justify-between gap-3"><dt class="text-slate-500">Vận chuyển dự kiến</dt><dd :class="shippingFee === 0 ? 'font-medium text-emerald-700' : 'font-medium'">{{ shippingFee === 0 ? 'Miễn phí' : formatMoney(shippingFee) }}</dd></div>
      <div class="flex justify-between gap-3 pt-2 text-base"><dt class="font-bold text-slate-900">Tổng dự kiến</dt><dd class="font-bold text-blue-700">{{ formatMoney(unitPrice * quantity + shippingFee) }}</dd></div>
    </dl>
    <div class="mt-4 grid gap-2">
      <button type="button" :disabled="!purchasable" class="rounded-lg bg-orange-600 px-4 py-3 text-sm font-bold text-white hover:bg-orange-700 disabled:opacity-45" @click="emit('buy')">Mua ngay</button>
      <button type="button" :disabled="!purchasable" class="rounded-lg border border-blue-700 px-4 py-3 text-sm font-bold text-blue-700 hover:bg-blue-50 disabled:opacity-45" @click="emit('add')">Thêm vào giỏ</button>
    </div>
  </aside>
</template>

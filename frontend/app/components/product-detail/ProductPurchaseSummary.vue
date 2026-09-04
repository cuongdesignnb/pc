<script setup lang="ts">
import type { ProductDetail, ProductVariant } from '~/types/product-detail'

const props = defineProps<{
  product: ProductDetail
  variant: ProductVariant | null
  quantity: number
  purchasable: boolean
}>()
const emit = defineEmits<{ add: []; buy: [] }>()
const { formatMoney, getString } = useSettings()

const unitPrice = computed(() => props.variant?.pricing.display_price ?? props.product.pricing.display_price)
const originalUnitPrice = computed(() => props.variant?.pricing.price ?? props.product.pricing.price)
const subtotal = computed(() => unitPrice.value * props.quantity)
const discount = computed(() => Math.max(0, originalUnitPrice.value - unitPrice.value) * props.quantity)
const benefits = computed(() => [
  { icon: '◈', text: getString('storefront_authenticity_message') },
  { icon: '↻', text: getString('storefront_return_policy_short') },
  { icon: '▣', text: getString('storefront_delivery_policy_short') },
  { icon: '♧', text: getString('storefront_technical_support_short') },
].filter(item => item.text))
</script>

<template>
  <aside class="pdp-panel pdp-summary-card lg:sticky lg:top-24">
    <h2 class="text-sm font-bold text-slate-900">Tóm tắt đơn hàng</h2>
    <div class="mt-3 flex gap-2.5">
      <NuxtImg
        v-if="product.images[0]?.url"
        :src="product.images[0].url"
        :alt="product.images[0].alt || product.name"
        width="64"
        height="64"
        class="h-16 w-16 shrink-0 rounded-[6px] border border-slate-100 object-contain"
      />
      <div class="min-w-0">
        <p class="line-clamp-2 text-xs font-semibold leading-4 text-slate-800">{{ product.name }}</p>
        <p v-if="variant" class="mt-1 text-[10px] text-slate-500">{{ variant.name }}</p>
        <p class="mt-1 text-xs font-bold text-red-600">{{ formatMoney(unitPrice) }}</p>
      </div>
    </div>

    <dl class="mt-3 space-y-2 border-y border-slate-100 py-3 text-xs">
      <div class="flex justify-between gap-3"><dt class="text-slate-500">Số lượng</dt><dd class="font-medium text-slate-800">{{ quantity }}</dd></div>
      <div class="flex justify-between gap-3"><dt class="text-slate-500">Tạm tính</dt><dd class="font-medium text-slate-800">{{ formatMoney(subtotal) }}</dd></div>
      <div class="flex justify-between gap-3"><dt class="text-slate-500">Giảm giá</dt><dd class="font-medium text-red-600">{{ discount > 0 ? `-${formatMoney(discount)}` : '—' }}</dd></div>
      <div class="flex justify-between gap-3 pt-1 text-sm"><dt class="font-bold text-slate-900">Tổng cộng</dt><dd class="font-bold text-red-600">{{ formatMoney(subtotal) }}</dd></div>
    </dl>

    <div class="mt-3 grid gap-2">
      <button type="button" :disabled="!purchasable" class="h-9 rounded-[6px] bg-orange-600 px-3 text-xs font-bold uppercase text-white hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-45" @click="emit('buy')">Mua ngay</button>
      <button type="button" :disabled="!purchasable" class="h-9 rounded-[6px] bg-blue-700 px-3 text-xs font-bold uppercase text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-45" @click="emit('add')">Thêm vào giỏ</button>
    </div>

    <div v-if="benefits.length" class="mt-3 space-y-2 border-t border-slate-100 pt-3">
      <div v-for="benefit in benefits" :key="benefit.text" class="flex items-start gap-2 text-[10px] leading-4 text-slate-600">
        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs text-blue-700" aria-hidden="true">{{ benefit.icon }}</span>
        <span>{{ benefit.text }}</span>
      </div>
    </div>
  </aside>
</template>

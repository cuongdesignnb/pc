<script setup lang="ts">
const props = defineProps<{ subtotal: number }>()
const { shippingFreeThreshold, shippingDefaultFee, warehouseAddresses, formatMoney } = useSettings()
const isFreeShipping = computed(() => shippingFreeThreshold.value > 0 && props.subtotal >= shippingFreeThreshold.value)
</script>

<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-5">
    <h2 class="text-base font-bold text-slate-900">Giao hàng & nhận tại cửa hàng</h2>
    <p class="mt-2 text-sm text-slate-600">Phí giao hàng được xác nhận lại khi đặt đơn.</p>
    <div class="mt-3 flex items-center justify-between gap-4 text-sm"><span class="text-slate-600">Phí dự kiến</span><strong>{{ isFreeShipping ? 'Miễn phí' : formatMoney(shippingDefaultFee) }}</strong></div>
    <p v-if="warehouseAddresses" class="mt-4 whitespace-pre-line border-t border-slate-100 pt-4 text-sm leading-6 text-slate-700">{{ warehouseAddresses }}</p>
    <p v-else class="mt-4 border-t border-slate-100 pt-4 text-sm text-slate-600">Liên hệ để kiểm tra phương án nhận hàng phù hợp.</p>
  </section>
</template>

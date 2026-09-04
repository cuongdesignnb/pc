<script setup lang="ts">
const props = withDefaults(defineProps<{ warrantyMonths?: number | null }>(), {
  warrantyMonths: null,
})
const { getString } = useSettings()

const benefits = computed(() => [
  { icon: '◆', title: 'Cam kết chính hãng', text: getString('storefront_authenticity_message') },
  { icon: '▰', title: 'Freeship toàn quốc', text: getString('storefront_delivery_policy_short') },
  { icon: '✓', title: 'Bảo hành chính hãng', text: getString('storefront_warranty_information') || (props.warrantyMonths ? `${props.warrantyMonths} tháng theo sản phẩm` : '') },
  { icon: '↻', title: 'Đổi trả dễ dàng', text: getString('storefront_return_policy_short') },
  { icon: '♧', title: 'Hỗ trợ kỹ thuật', text: getString('storefront_technical_support_short') },
].filter(item => item.text))
</script>

<template>
  <section v-if="benefits.length" aria-label="Chính sách dịch vụ" class="pdp-benefit-strip">
    <div v-for="benefit in benefits" :key="benefit.title" class="pdp-benefit-item">
      <span class="pdp-benefit-icon" aria-hidden="true">{{ benefit.icon }}</span>
      <div class="min-w-0">
        <strong class="block text-[11px] leading-4 text-slate-800">{{ benefit.title }}</strong>
        <span class="mt-0.5 block line-clamp-2 text-[10px] leading-4 text-slate-500">{{ benefit.text }}</span>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
const { getString } = useSettings()
const benefits = computed(() => [
  { title: 'Cam kết', text: getString('storefront_authenticity_message') },
  { title: 'Đổi trả', text: getString('storefront_return_policy_short') },
  { title: 'Giao hàng', text: getString('storefront_delivery_policy_short') },
  { title: 'Hỗ trợ', text: getString('storefront_technical_support_short') },
  { title: 'Trả góp', text: getString('storefront_installment_message') },
].filter(item => item.text))
</script>

<template>
  <section v-if="benefits.length" aria-label="Chính sách dịch vụ" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
    <div v-for="benefit in benefits" :key="benefit.title" class="flex gap-2 text-sm"><span class="text-blue-600" aria-hidden="true">✓</span><p><strong class="block text-slate-800">{{ benefit.title }}</strong><span class="text-slate-600">{{ benefit.text }}</span></p></div>
  </section>
</template>

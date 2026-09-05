<script setup lang="ts">
const { getString } = useSettings()

const services = computed(() => [
  { key: 'shipping', icon: 'truck', title: getString('homepage_service_shipping_title', 'Giao hàng nhanh'), text: getString('homepage_service_shipping_text', 'Theo chính sách cửa hàng') },
  { key: 'authenticity', icon: 'shield', title: getString('homepage_service_authenticity_title', 'Hàng chính hãng'), text: getString('homepage_service_authenticity_text', 'Nguồn gốc rõ ràng') },
  { key: 'installment', icon: 'coin', title: getString('homepage_service_installment_title', 'Trả góp'), text: getString('homepage_service_installment_text', 'Theo phương thức hỗ trợ') },
  { key: 'support', icon: 'headset', title: getString('homepage_service_support_title', 'Hỗ trợ kỹ thuật'), text: getString('homepage_service_support_text', 'Tư vấn tận tâm') },
  { key: 'returns', icon: 'refresh', title: getString('homepage_service_returns_title', 'Đổi trả dễ dàng'), text: getString('homepage_service_returns_text', 'Theo chính sách đổi trả') },
])
</script>

<template>
  <section class="home-service-section">
    <div class="pc-container">
      <div class="home-service-strip">
        <div v-for="service in services" :key="service.key" class="home-service-item">
          <svg v-if="service.icon === 'truck'" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 6h11v10H3zM14 10h4l3 3v3h-7zM7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM18 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" /></svg>
          <svg v-else-if="service.icon === 'shield'" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linejoin="round" stroke-width="1.5" d="M12 3 20 6v5c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-3Z" /><path stroke-linecap="round" stroke-width="1.5" d="m8.5 12 2.2 2.2 4.8-5" /></svg>
          <svg v-else-if="service.icon === 'coin'" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="8.5" stroke-width="1.5" /><path stroke-linecap="round" stroke-width="1.5" d="M14.5 9.2c-.5-.6-1.3-.9-2.3-.9-1.1 0-2 .6-2 1.5 0 2.1 4.3 1.1 4.3 3.4 0 .9-.9 1.5-2.1 1.5-1 0-1.8-.3-2.4-1" /><path stroke-linecap="round" stroke-width="1.5" d="M12 7v10" /></svg>
          <svg v-else-if="service.icon === 'headset'" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.5" d="M4 14v-2a8 8 0 0 1 16 0v2M4 14h3v5H5a1 1 0 0 1-1-1v-4ZM20 14h-3v5h2a1 1 0 0 0 1-1v-4Z" /><path stroke-linecap="round" stroke-width="1.5" d="M17 19c0 1.1-1.3 2-3 2h-1" /></svg>
          <svg v-else aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 12a8 8 0 0 1 14-5l2 2M20 12a8 8 0 0 1-14 5l-2-2" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 5v4h-4M4 19v-4h4" /></svg>
          <span><strong>{{ service.title }}</strong><small>{{ service.text }}</small></span>
        </div>
      </div>
    </div>
  </section>
</template>

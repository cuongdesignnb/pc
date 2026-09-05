<script setup lang="ts">
const config = useRuntimeConfig()
const { siteName } = useSettings()
const email = ref('')
const status = ref<'idle' | 'loading' | 'success' | 'error'>('idle')
const message = ref('')

function errorMessage(error: unknown): string {
  if (typeof error === 'object' && error !== null && 'data' in error) {
    const data = (error as { data?: { message?: string } }).data
    if (data?.message) return data.message
  }
  return 'Không thể đăng ký lúc này. Vui lòng thử lại.'
}

async function subscribe() {
  status.value = 'loading'
  message.value = ''
  try {
    const response = await $fetch<{ message: string }>(`${config.public.apiBase}/newsletter/subscribe`, {
      method: 'POST',
      body: { email: email.value.trim() },
    })
    status.value = 'success'
    message.value = response.message
    email.value = ''
  } catch (error: unknown) {
    status.value = 'error'
    message.value = errorMessage(error)
  }
}
</script>

<template>
  <div class="footer-newsletter">
    <h3>ĐĂNG KÝ NHẬN TIN</h3>
    <p>Nhận thông tin ưu đãi mới nhất từ {{ siteName }}</p>
    <form class="footer-newsletter-form" @submit.prevent="subscribe">
      <label class="sr-only" for="footer-newsletter-email">Email nhận tin</label>
      <input id="footer-newsletter-email" v-model="email" type="email" required maxlength="255" placeholder="Nhập email của bạn">
      <button type="submit" :disabled="status === 'loading'">
        {{ status === 'loading' ? '...' : 'ĐĂNG KÝ' }}
      </button>
    </form>
    <p v-if="message" class="footer-newsletter-message" :class="status === 'error' ? 'is-error' : 'is-success'" role="status">
      {{ message }}
    </p>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{ slug: string }>()
const { token, isAuthenticated, user } = useAuth()
const config = useRuntimeConfig()
const toast = useToast()
const form = reactive({ rating: 5, title: '', body: '', guest_name: '', guest_email: '' })
const submitting = ref(false)
const canSubmit = computed(() => form.body.trim().length >= 10 && (isAuthenticated.value || (form.guest_name.trim() && form.guest_email.trim())))

const submit = async () => {
  if (!canSubmit.value || submitting.value) return
  submitting.value = true
  try {
    await $fetch(`${config.public.apiBase}/products/${encodeURIComponent(props.slug)}/reviews`, {
      method: 'POST',
      headers: token.value ? { Authorization: `Bearer ${token.value}` } : undefined,
      body: form,
    })
    form.title = ''
    form.body = ''
    if (!isAuthenticated.value) {
      form.guest_name = ''
      form.guest_email = ''
    }
    toast.add({ title: 'Đã gửi đánh giá', description: 'Đánh giá sẽ hiển thị sau khi được duyệt.', color: 'success' })
  } catch (error: any) {
    toast.add({ title: 'Không thể gửi đánh giá', description: error?.data?.message || 'Vui lòng kiểm tra lại thông tin.', color: 'error' })
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <section class="pdp-panel">
    <details>
      <summary class="cursor-pointer text-xs font-bold text-blue-700">Viết đánh giá</summary>
      <p class="mt-2 text-[11px] text-slate-500">Đánh giá được kiểm duyệt trước khi công khai.</p>
      <p v-if="isAuthenticated" class="mt-3 text-xs text-slate-600">Đang đăng nhập: {{ user?.name }}</p>
      <form class="mt-3 space-y-3" @submit.prevent="submit">
        <fieldset><legend class="mb-1 text-xs font-medium text-slate-700">Số sao</legend><div class="flex gap-1"><button v-for="star in 5" :key="star" type="button" class="rounded p-1 text-xl" :class="star <= form.rating ? 'text-amber-500' : 'text-slate-300'" :aria-label="`${star} sao`" :aria-pressed="star === form.rating" @click="form.rating = star">★</button></div></fieldset>
        <div v-if="!isAuthenticated" class="grid gap-2 sm:grid-cols-2"><label class="text-xs font-medium text-slate-700">Họ tên<input v-model="form.guest_name" required class="mt-1 h-8 w-full rounded-[6px] border border-slate-200 px-2 text-xs font-normal" /></label><label class="text-xs font-medium text-slate-700">Email<input v-model="form.guest_email" required type="email" class="mt-1 h-8 w-full rounded-[6px] border border-slate-200 px-2 text-xs font-normal" /></label></div>
        <label class="block text-xs font-medium text-slate-700">Tiêu đề (không bắt buộc)<input v-model="form.title" maxlength="150" class="mt-1 h-8 w-full rounded-[6px] border border-slate-200 px-2 text-xs font-normal" /></label>
        <label class="block text-xs font-medium text-slate-700">Nội dung<textarea v-model="form.body" required minlength="10" maxlength="2000" rows="4" class="mt-1 w-full rounded-[6px] border border-slate-200 px-2 py-1.5 text-xs font-normal" /></label>
        <button type="submit" :disabled="!canSubmit || submitting" class="h-8 rounded-[6px] bg-blue-700 px-3 text-xs font-bold text-white hover:bg-blue-800 disabled:opacity-45">{{ submitting ? 'Đang gửi…' : 'Gửi đánh giá' }}</button>
      </form>
    </details>
  </section>
</template>

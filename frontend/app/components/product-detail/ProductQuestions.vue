<script setup lang="ts">
import type { ProductQuestion } from '~/types/product-detail'

const props = defineProps<{ slug: string }>()
const { token, isAuthenticated, user } = useAuth()
const config = useRuntimeConfig()
const toast = useToast()
const questions = ref<ProductQuestion[]>([])
const meta = ref({ current_page: 1, last_page: 1 })
const loading = ref(false)
const submitting = ref(false)
const form = reactive({ body: '', guest_name: '', guest_email: '' })

const load = async (page = 1) => {
  loading.value = true
  try {
    const response = await $fetch<{ questions: ProductQuestion[]; meta: { current_page: number; last_page: number } }>(`${config.public.apiBase}/products/${encodeURIComponent(props.slug)}/questions`, { params: { page, per_page: 10 } })
    questions.value = response.questions
    meta.value = response.meta
  } finally { loading.value = false }
}
const submit = async () => {
  if (!form.body.trim() || (!isAuthenticated.value && (!form.guest_name.trim() || !form.guest_email.trim()))) return
  submitting.value = true
  try {
    await $fetch(`${config.public.apiBase}/products/${encodeURIComponent(props.slug)}/questions`, { method: 'POST', headers: token.value ? { Authorization: `Bearer ${token.value}` } : undefined, body: form })
    form.body = ''
    if (!isAuthenticated.value) { form.guest_name = ''; form.guest_email = '' }
    toast.add({ title: 'Đã gửi câu hỏi', description: 'Câu hỏi sẽ hiển thị sau khi được duyệt.', color: 'success' })
  } catch (error: any) {
    toast.add({ title: 'Không thể gửi câu hỏi', description: error?.data?.message || 'Vui lòng kiểm tra lại thông tin.', color: 'error' })
  } finally { submitting.value = false }
}
onMounted(() => load())
const date = (value: string | null) => value ? new Intl.DateTimeFormat('vi-VN').format(new Date(value)) : ''
</script>

<template>
  <section id="hoi-dap" class="scroll-mt-28 rounded-2xl border border-slate-200 bg-white p-5 lg:p-7">
    <h2 class="text-xl font-bold text-slate-900">Hỏi đáp về sản phẩm</h2>
    <form class="mt-4 rounded-xl bg-slate-50 p-4" @submit.prevent="submit"><p v-if="isAuthenticated" class="mb-3 text-sm text-slate-600">Đang đăng nhập: {{ user?.name }}</p><div v-if="!isAuthenticated" class="mb-3 grid gap-3 sm:grid-cols-2"><input v-model="form.guest_name" required placeholder="Họ tên" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" /><input v-model="form.guest_email" required type="email" placeholder="Email" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" /></div><textarea v-model="form.body" required minlength="5" maxlength="2000" rows="3" placeholder="Đặt câu hỏi của bạn…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /><button type="submit" :disabled="submitting" class="mt-3 rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-45">{{ submitting ? 'Đang gửi…' : 'Gửi câu hỏi' }}</button></form>
    <p v-if="loading" class="mt-5 text-sm text-slate-500">Đang tải câu hỏi…</p>
    <div v-else-if="questions.length" class="mt-5 divide-y divide-slate-200"><article v-for="question in questions" :key="question.id" class="py-4"><div class="flex flex-wrap gap-x-3 gap-y-1"><strong class="text-sm text-slate-800">{{ question.asker_name }}</strong><time class="text-xs text-slate-500">{{ date(question.created_at) }}</time></div><p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ question.body }}</p><div v-for="answer in question.answers" :key="answer.id" class="mt-3 rounded-lg bg-blue-50 p-3 text-sm text-blue-950"><strong>{{ answer.is_official ? 'Phản hồi từ cửa hàng' : answer.author_name }}</strong><p class="mt-1 whitespace-pre-line">{{ answer.body }}</p></div></article></div>
    <p v-else class="mt-5 text-sm text-slate-500">Chưa có câu hỏi nào.</p>
    <div v-if="meta.last_page > 1" class="mt-4 flex gap-2"><button type="button" :disabled="meta.current_page <= 1" class="rounded border px-3 py-2 text-sm disabled:opacity-40" @click="load(meta.current_page - 1)">Trước</button><button type="button" :disabled="meta.current_page >= meta.last_page" class="rounded border px-3 py-2 text-sm disabled:opacity-40" @click="load(meta.current_page + 1)">Sau</button></div>
  </section>
</template>

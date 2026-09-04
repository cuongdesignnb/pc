<script setup lang="ts">
import type { ProductQuestion } from '~/types/product-detail'

const props = defineProps<{ slug: string }>()
const { token, isAuthenticated, user } = useAuth()
const config = useRuntimeConfig()
const toast = useToast()
const questions = ref<ProductQuestion[]>([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(false)
const submitting = ref(false)
const showDialog = ref(false)
const form = reactive({ body: '', guest_name: '', guest_email: '' })

const load = async (page = 1) => {
  loading.value = true
  try {
    const response = await $fetch<{ questions: ProductQuestion[]; meta: { current_page: number; last_page: number; total: number } }>(`${config.public.apiBase}/products/${encodeURIComponent(props.slug)}/questions`, { params: { page, per_page: 3 } })
    questions.value = response.questions || []
    meta.value = response.meta
  } catch {
    questions.value = []
  } finally {
    loading.value = false
  }
}

const submit = async () => {
  if (!form.body.trim() || (!isAuthenticated.value && (!form.guest_name.trim() || !form.guest_email.trim()))) return
  submitting.value = true
  try {
    await $fetch(`${config.public.apiBase}/products/${encodeURIComponent(props.slug)}/questions`, { method: 'POST', headers: token.value ? { Authorization: `Bearer ${token.value}` } : undefined, body: form })
    form.body = ''
    if (!isAuthenticated.value) { form.guest_name = ''; form.guest_email = '' }
    showDialog.value = false
    toast.add({ title: 'Đã gửi câu hỏi', description: 'Câu hỏi sẽ hiển thị sau khi được duyệt.', color: 'success' })
    await load()
  } catch (error: any) {
    toast.add({ title: 'Không thể gửi câu hỏi', description: error?.data?.message || 'Vui lòng kiểm tra lại thông tin.', color: 'error' })
  } finally {
    submitting.value = false
  }
}

onMounted(() => load())
const date = (value: string | null) => value ? new Intl.DateTimeFormat('vi-VN').format(new Date(value)) : ''
</script>

<template>
  <section id="hoi-dap" class="pdp-panel scroll-mt-28">
    <div class="flex items-center justify-between gap-2">
      <div><h2 class="pdp-section-title">Hỏi đáp</h2><p class="mt-1 text-[10px] text-slate-500">Giải đáp nhanh từ khách hàng và cửa hàng</p></div>
      <button type="button" class="h-8 shrink-0 rounded-[6px] border border-blue-400 px-2.5 text-[10px] font-bold text-blue-700 hover:bg-blue-50" @click="showDialog = true">Đặt câu hỏi</button>
    </div>
    <p v-if="loading" class="mt-3 text-xs text-slate-500">Đang tải câu hỏi…</p>
    <div v-else-if="questions.length" class="mt-3 divide-y divide-slate-100">
      <article v-for="question in questions" :key="question.id" class="py-2.5 first:pt-0">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1"><strong class="text-[11px] text-slate-800">{{ question.asker_name }}</strong><time class="text-[9px] text-slate-400">{{ date(question.created_at) }}</time></div>
        <p class="mt-1 text-[11px] leading-4 text-slate-700">{{ question.body }}</p>
        <div v-if="question.answers[0]" class="mt-1.5 border-l-2 border-blue-200 pl-2 text-[10px] leading-4 text-slate-600"><strong class="text-blue-700">{{ question.answers[0].is_official ? 'PC Shop' : question.answers[0].author_name }}:</strong> {{ question.answers[0].body }}</div>
      </article>
    </div>
    <p v-else-if="!loading" class="mt-3 text-xs text-slate-500">Chưa có câu hỏi nào.</p>
    <div v-if="meta.last_page > 1" class="mt-2 flex items-center justify-between border-t border-slate-100 pt-2"><button type="button" :disabled="meta.current_page <= 1 || loading" class="text-[10px] font-semibold text-blue-700 disabled:opacity-40" @click="load(meta.current_page - 1)">‹ Trước</button><span class="text-[10px] text-slate-400">{{ meta.current_page }}/{{ meta.last_page }}</span><button type="button" :disabled="meta.current_page >= meta.last_page || loading" class="text-[10px] font-semibold text-blue-700 disabled:opacity-40" @click="load(meta.current_page + 1)">Sau ›</button></div>
  </section>

  <Teleport to="body">
    <div v-if="showDialog" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/45 p-4" role="dialog" aria-modal="true" aria-label="Đặt câu hỏi" @click.self="showDialog = false">
      <div class="w-full max-w-md rounded-[8px] bg-white p-4 shadow-xl">
        <div class="flex items-center justify-between gap-3"><h2 class="text-base font-bold text-slate-900">Đặt câu hỏi</h2><button type="button" class="text-xl leading-none text-slate-400 hover:text-slate-700" aria-label="Đóng" @click="showDialog = false">×</button></div>
        <p v-if="isAuthenticated" class="mt-2 text-xs text-slate-500">Đang đăng nhập: {{ user?.name }}</p>
        <form class="mt-3 space-y-2.5" @submit.prevent="submit">
          <div v-if="!isAuthenticated" class="grid gap-2 sm:grid-cols-2"><label class="text-xs font-medium text-slate-700">Họ tên<input v-model="form.guest_name" required class="mt-1 h-9 w-full rounded-[6px] border border-slate-200 px-2 text-xs font-normal" /></label><label class="text-xs font-medium text-slate-700">Email<input v-model="form.guest_email" required type="email" class="mt-1 h-9 w-full rounded-[6px] border border-slate-200 px-2 text-xs font-normal" /></label></div>
          <label class="block text-xs font-medium text-slate-700">Nội dung<textarea v-model="form.body" required minlength="5" maxlength="2000" rows="4" placeholder="Đặt câu hỏi của bạn…" class="mt-1 w-full rounded-[6px] border border-slate-200 px-2 py-1.5 text-xs font-normal" /></label>
          <button type="submit" :disabled="submitting" class="h-9 rounded-[6px] bg-blue-700 px-4 text-xs font-bold text-white hover:bg-blue-800 disabled:opacity-45">{{ submitting ? 'Đang gửi…' : 'Gửi câu hỏi' }}</button>
        </form>
      </div>
    </div>
  </Teleport>
</template>

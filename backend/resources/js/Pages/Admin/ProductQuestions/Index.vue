<script setup>
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ questions: Object, filters: Object });
const flash = usePage().props.flash;
const search = ref(props.filters?.search || '');
const filterApproved = ref(props.filters?.is_approved ?? '');
const answeringId = ref(null);
const answerText = ref('');
let timer;

watch([search, filterApproved], () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get('/admin/product-questions', {
        search: search.value || undefined,
        is_approved: filterApproved.value !== '' ? filterApproved.value : undefined,
    }, { preserveState: true, replace: true }), 350);
});

function approve(id) { router.patch(`/admin/product-questions/${id}/approve`, {}, { preserveState: true }); }
function reject(id) { router.patch(`/admin/product-questions/${id}/reject`, {}, { preserveState: true }); }
function remove(id) { if (confirm('Xóa câu hỏi và các câu trả lời?')) router.delete(`/admin/product-questions/${id}`); }
function submitAnswer(id) {
    router.post(`/admin/product-questions/${id}/answers`, { body: answerText.value }, {
        preserveState: true,
        onSuccess: () => { answeringId.value = null; answerText.value = ''; },
    });
}
function toggleAnswer(id) { router.patch(`/admin/product-question-answers/${id}`, {}, { preserveState: true }); }
function formatDate(date) { return date ? new Date(date).toLocaleString('vi-VN') : '—'; }
</script>

<template>
<AdminLayout title="Hỏi đáp sản phẩm">
    <div v-if="flash?.success" class="mb-4 rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-3 text-sm text-emerald-400">{{ flash.success }}</div>
    <div class="mb-4 flex flex-wrap gap-3"><input v-model="search" placeholder="Tìm sản phẩm hoặc nội dung..." class="w-64 border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><select v-model="filterApproved" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><option value="">Tất cả trạng thái</option><option value="1">Đã duyệt</option><option value="0">Chờ duyệt</option></select></div>
    <div class="space-y-3">
        <article v-for="question in questions.data" :key="question.id" class="rounded-lg border border-slate-800/60 bg-slate-900 p-5">
            <div class="flex flex-col justify-between gap-3 md:flex-row">
                <div class="min-w-0"><p class="text-sm font-semibold text-slate-200">{{ question.product?.name || 'Sản phẩm đã xóa' }}</p><p class="mt-1 text-xs text-slate-500">{{ question.user?.name || question.guest_name || 'Khách vãng lai' }} · {{ formatDate(question.created_at) }}</p><p class="mt-3 whitespace-pre-line text-sm text-slate-300">{{ question.body }}</p></div>
                <div class="flex shrink-0 items-start gap-2"><span :class="question.is_approved ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-amber-500/30 bg-amber-500/10 text-amber-300'" class="rounded-full border px-2 py-1 text-xs">{{ question.is_approved ? 'Đã duyệt' : 'Chờ duyệt' }}</span><button v-if="!question.is_approved" @click="approve(question.id)" class="text-xs text-emerald-400">Duyệt</button><button v-else @click="reject(question.id)" class="text-xs text-amber-400">Ẩn</button><button @click="remove(question.id)" class="text-xs text-red-400">Xóa</button></div>
            </div>
            <div v-if="question.answers?.length" class="mt-4 space-y-2 border-l-2 border-cyan-500/40 pl-4">
                <div v-for="answer in question.answers" :key="answer.id" :class="answer.is_approved ? 'bg-slate-950/50' : 'bg-amber-500/5'" class="rounded-lg p-3"><div class="flex justify-between gap-3"><div><p class="text-xs font-semibold text-cyan-300">{{ answer.user?.name || (answer.is_official ? 'PC Shop' : 'Nhân viên') }}</p><p class="mt-1 whitespace-pre-line text-sm text-slate-300">{{ answer.body }}</p></div><button @click="toggleAnswer(answer.id)" class="shrink-0 text-xs text-slate-400">{{ answer.is_approved ? 'Ẩn' : 'Duyệt' }}</button></div></div>
            </div>
            <div v-if="answeringId === question.id" class="mt-4 flex gap-2"><textarea v-model="answerText" rows="2" placeholder="Nhập câu trả lời chính thức..." class="min-w-0 flex-1 border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></textarea><div class="flex flex-col gap-2"><button @click="submitAnswer(question.id)" :disabled="!answerText.trim()" class="rounded-lg bg-cyan-600 px-3 py-2 text-sm text-white disabled:opacity-50">Gửi</button><button @click="answeringId = null" class="text-sm text-slate-400">Hủy</button></div></div>
            <button v-else @click="answeringId = question.id; answerText = ''" class="mt-4 text-sm text-cyan-300 hover:text-cyan-200">+ Trả lời chính thức</button>
        </article>
        <div v-if="!questions.data?.length" class="rounded-lg border border-dashed border-slate-700 p-8 text-center text-sm text-slate-500">Chưa có câu hỏi phù hợp.</div>
    </div>
    <div v-if="questions.last_page > 1" class="mt-5 flex justify-center gap-1"><template v-for="link in questions.links" :key="link.label"><button v-if="link.url" @click="router.get(link.url, {}, { preserveState: true })" :class="link.active ? 'bg-cyan-600 text-white' : 'bg-slate-900 text-slate-300'" class="rounded border px-3 py-1 text-sm" v-html="link.label"></button><span v-else class="px-3 py-1 text-sm text-slate-500" v-html="link.label"></span></template></div>
</AdminLayout>
</template>

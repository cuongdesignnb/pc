<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ schedules: Object, categories: Array, products: Array, configured: Boolean });
const initialForm = () => ({ topic: '', keywords: '', type: 'article', tone: 'professional', length: 'medium', full_article: true, with_images: false, image_count: 0, auto_publish: false, category_id: null, product_id: null, scheduled_at: '' });
const form = ref(initialForm());
const error = ref('');
const saving = ref(false);
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

async function save() {
    saving.value = true; error.value = '';
    try {
        await window.axios.post('/admin/ai-writer/schedules', form.value, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
        router.reload({ only: ['schedules'] });
        form.value = initialForm();
    } catch (e) { error.value = e.response?.data?.message || 'Không thể lưu lịch AI.'; }
    finally { saving.value = false; }
}
async function cancel(id) { if (!confirm('Hủy lịch này?')) return; await window.axios.delete(`/admin/ai-writer/schedules/${id}`, { headers: { 'X-CSRF-TOKEN': csrf() } }); router.reload({ only: ['schedules'] }); }
</script>
<template>
<AdminLayout title="Lịch viết AI">
    <div class="max-w-6xl space-y-6">
        <div><h3 class="text-xl font-bold text-slate-200">AI viết bài & mô tả</h3><p class="mt-1 text-sm text-slate-400">Đặt lịch để hệ thống tự tạo bài tin tức hoặc cập nhật mô tả sản phẩm. Bài tự động luôn lưu nháp nếu không bật xuất bản.</p></div>
        <div v-if="!configured" class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-200">Chưa cấu hình API key AI nội dung. Hãy nhập trong Cài đặt hoặc biến môi trường máy chủ.</div>
        <form @submit.prevent="save" class="grid gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6 md:grid-cols-2">
            <label class="text-sm text-slate-300 md:col-span-2">Chủ đề / tên sản phẩm *<input v-model="form.topic" required class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2" /></label>
            <label class="text-sm text-slate-300 md:col-span-2">Từ khóa<textarea v-model="form.keywords" rows="2" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2" /></label>
            <label class="text-sm text-slate-300">Loại nội dung<select v-model="form.type" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2"><option value="article">Bài tin tức</option><option value="product_description">Mô tả sản phẩm</option><option value="category_description">Mô tả danh mục</option><option value="seo">SEO</option></select></label>
            <label class="text-sm text-slate-300">Sản phẩm (với mô tả sản phẩm)<select v-model="form.product_id" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2"><option value="">— Chọn sản phẩm —</option><option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option></select></label>
            <label class="text-sm text-slate-300">Danh mục bài viết<select v-model="form.category_id" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2"><option value="">— Không chọn —</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></label>
            <label class="text-sm text-slate-300">Thời điểm chạy *<input v-model="form.scheduled_at" required type="datetime-local" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2" /></label>
            <div class="flex flex-wrap items-end gap-4 md:col-span-2"><label class="flex items-center gap-2 text-sm text-slate-300"><input v-model="form.with_images" type="checkbox" class="rounded text-violet-500" /> Sinh ảnh minh họa</label><label class="flex items-center gap-2 text-sm text-slate-300"><input v-model="form.auto_publish" type="checkbox" class="rounded text-cyan-500" /> Tự xuất bản khi hoàn tất</label><button :disabled="saving" class="ml-auto rounded-lg bg-cyan-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ saving ? 'Đang lưu…' : 'Đặt lịch' }}</button></div>
            <p v-if="error" class="text-sm text-red-300 md:col-span-2">{{ error }}</p>
        </form>
        <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900"><table class="w-full text-sm"><thead class="bg-slate-800/50 text-left text-xs uppercase text-slate-400"><tr><th class="px-4 py-3">Chủ đề</th><th class="px-4 py-3">Loại</th><th class="px-4 py-3">Lịch chạy</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3"></th></tr></thead><tbody class="divide-y divide-slate-800"> <tr v-for="s in schedules?.data || []" :key="s.id"><td class="px-4 py-3 text-slate-200">{{ s.topic }}</td><td class="px-4 py-3 text-slate-400">{{ s.type }}</td><td class="px-4 py-3 text-slate-400">{{ new Date(s.scheduled_at).toLocaleString('vi-VN') }}</td><td class="px-4 py-3"><span class="rounded-full bg-slate-800 px-2 py-1 text-xs text-slate-300">{{ s.status }}</span></td><td class="px-4 py-3 text-right"><button v-if="s.status === 'pending'" @click="cancel(s.id)" class="text-xs text-red-300 hover:text-red-200">Hủy</button></td></tr><tr v-if="!(schedules?.data || []).length"><td colspan="5" class="px-4 py-8 text-center text-slate-500">Chưa có lịch nào.</td></tr></tbody></table></div>
    </div>
</AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ tokens: Array, types: Array, apiBase: String });
const page = usePage();
const flash = computed(() => page.props.flash || {});
const form = useForm({ name: '', type: props.types?.[0]?.value || 'content', expires_in_days: '' });
const copied = ref(false);

function submit() {
    form.transform(data => ({ ...data, expires_in_days: data.expires_in_days || null }))
        .post('/admin/api-tokens', { preserveScroll: true, onSuccess: () => { form.reset('name', 'expires_in_days'); copied.value = false; } });
}
function revoke(token) {
    if (!confirm(`Thu hồi token "${token.name}"? Công cụ đang dùng token này sẽ bị từ chối ngay.`)) return;
    router.delete(`/admin/api-tokens/${token.id}`, { preserveScroll: true });
}
async function copy(text) {
    try { await navigator.clipboard.writeText(text); copied.value = true; } catch { copied.value = false; }
}
function formatDate(value) {
    return value ? new Date(value).toLocaleString('vi-VN') : '—';
}
</script>

<template>
<AdminLayout title="API token">
    <div v-if="flash.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div v-if="flash.error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">{{ flash.error }}</div>

    <div v-if="flash.api_token" class="mb-4 p-4 rounded-lg border border-amber-500/40 bg-amber-500/10">
        <p class="text-sm font-medium text-amber-300 mb-2">Token mới (chỉ hiện một lần, hãy lưu vào công cụ cần dùng):</p>
        <div class="flex items-center gap-2">
            <code class="flex-1 break-all rounded bg-slate-950 px-3 py-2 text-sm text-slate-100">{{ flash.api_token }}</code>
            <button @click="copy(flash.api_token)" class="px-3 py-2 bg-cyan-600 text-white rounded-lg text-sm">{{ copied ? 'Đã chép' : 'Sao chép' }}</button>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <form @submit.prevent="submit" class="bg-slate-900 rounded-lg border border-slate-800/60 p-4 space-y-3">
            <h2 class="text-base font-semibold text-slate-200">Tạo token</h2>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Tên (để nhận biết, ví dụ "Viết bài AI", "KIOT")</label>
                <input v-model="form.name" required maxlength="100" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <p v-if="form.errors.name" class="text-xs text-red-400 mt-1">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Quyền</label>
                <select v-model="form.type" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                    <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Hết hạn sau (ngày, để trống = không hết hạn)</label>
                <input v-model="form.expires_in_days" type="number" min="1" max="3650" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
            </div>
            <button :disabled="form.processing" class="w-full px-4 py-2 bg-cyan-600 text-white rounded-lg text-sm font-medium disabled:opacity-50">Tạo token</button>
            <div class="text-xs text-slate-500 space-y-1 pt-2 border-t border-slate-800/60">
                <p>Gửi kèm header <code>Authorization: Bearer &lt;token&gt;</code> và <code>Accept: application/json</code>.</p>
                <p>Địa chỉ API: <code class="break-all">{{ apiBase }}</code></p>
                <p>Đọc: <code>GET /products</code>, <code>/products/{id}</code>, <code>/categories</code>, <code>/posts</code>, <code>/posts/{id}</code>, <code>/media</code>.</p>
                <p>Ghi (token Nội dung): <code>PATCH /products/{id}</code> (meta_title, meta_description, short_description, description, images[].alt), <code>POST/PATCH /categories</code>, <code>POST/PATCH /posts</code>, <code>POST /media</code> (files[]), <code>PATCH /media/{id}</code>. Không sửa được giá, tồn kho, danh mục sản phẩm và slug.</p>
            </div>
        </form>

        <div class="lg:col-span-2 bg-slate-900 rounded-lg border border-slate-800/60 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-800/40 text-sm">
                <thead class="bg-slate-800/40"><tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Tên</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Quyền</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Dùng lần cuối</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Hết hạn</th>
                    <th class="px-4 py-3"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-800/40">
                    <tr v-for="token in tokens" :key="token.id">
                        <td class="px-4 py-3 text-slate-200">{{ token.name }}<div class="text-xs text-slate-500">Tạo {{ formatDate(token.created_at) }} bởi {{ token.created_by || '—' }}</div></td>
                        <td class="px-4 py-3 text-slate-400">{{ token.type_label }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ formatDate(token.last_used_at) }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ formatDate(token.expires_at) }}</td>
                        <td class="px-4 py-3 text-right"><button @click="revoke(token)" class="text-red-400 hover:text-red-300">Thu hồi</button></td>
                    </tr>
                    <tr v-if="!tokens.length"><td colspan="5" class="px-4 py-8 text-center text-slate-500">Chưa có token nào</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</AdminLayout>
</template>

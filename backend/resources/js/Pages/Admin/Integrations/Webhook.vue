<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ endpoint: Object, events: Array, deliveries: Array, backoff: Array });
const page = usePage();
const flash = computed(() => page.props.flash || {});
const form = useForm({
    enabled: !!props.endpoint.enabled,
    url: props.endpoint.url || '',
    events: [...(props.endpoint.events || [])],
});
const copied = ref(false);
const busy = ref(false);

function save() {
    form.put('/admin/webhooks', { preserveScroll: true });
}
function generateSecret() {
    if (props.endpoint.has_secret && !confirm('Đổi secret? Bên nhận (hook.laptopplus.vn) phải cập nhật secret mới, nếu không sẽ từ chối chữ ký.')) return;
    router.post('/admin/webhooks/secret', {}, { preserveScroll: true, onSuccess: () => (copied.value = false) });
}
function sendTest() {
    router.post('/admin/webhooks/test', {}, { preserveScroll: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false) });
}
function retry(delivery) {
    router.post(`/admin/webhooks/deliveries/${delivery.id}/retry`, {}, { preserveScroll: true });
}
async function copy(text) {
    try { await navigator.clipboard.writeText(text); copied.value = true; } catch { copied.value = false; }
}
function formatDate(value) {
    return value ? new Date(value).toLocaleString('vi-VN') : '—';
}
const statusLabel = { pending: 'Đang chờ', sent: 'Đã gửi', failed: 'Lỗi, sẽ thử lại', dead: 'Ngừng gửi' };
const statusClass = { pending: 'text-slate-300', sent: 'text-emerald-400', failed: 'text-amber-300', dead: 'text-red-400' };
const backoffText = computed(() => (props.backoff || []).map((s) => (s >= 3600 ? `${s / 3600} giờ` : `${s / 60} phút`)).join(', '));
</script>

<template>
<AdminLayout title="Webhook">
    <div v-if="flash.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div v-if="flash.error" class="mb-4 p-3 bg-red-500/10 border border-red-500/20 text-red-400 rounded-lg text-sm">{{ flash.error }}</div>

    <div v-if="flash.webhook_secret" class="mb-4 p-4 rounded-lg border border-amber-500/40 bg-amber-500/10">
        <p class="text-sm font-medium text-amber-300 mb-2">Secret mới (chỉ hiện một lần, hãy lưu vào bên nhận webhook):</p>
        <div class="flex items-center gap-2">
            <code class="flex-1 break-all rounded bg-slate-950 px-3 py-2 text-sm text-slate-100">{{ flash.webhook_secret }}</code>
            <button @click="copy(flash.webhook_secret)" class="px-3 py-2 bg-cyan-600 text-white rounded-lg text-sm">{{ copied ? 'Đã chép' : 'Sao chép' }}</button>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <form @submit.prevent="save" class="bg-slate-900 rounded-lg border border-slate-800/60 p-4 space-y-4">
            <h2 class="text-base font-semibold text-slate-200">Cấu hình</h2>
            <label class="flex items-center justify-between gap-3 text-sm text-slate-300">
                <span>Bật webhook</span>
                <input v-model="form.enabled" type="checkbox">
            </label>
            <p v-if="form.errors.enabled" class="text-xs text-red-400">{{ form.errors.enabled }}</p>
            <div>
                <label class="block text-sm text-slate-300 mb-1">URL nhận (chỉ https://)</label>
                <input v-model="form.url" type="url" maxlength="500" placeholder="https://hook.laptopplus.vn/..." class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <p v-if="form.errors.url" class="text-xs text-red-400 mt-1">{{ form.errors.url }}</p>
            </div>
            <div>
                <p class="text-sm text-slate-300 mb-1">Sự kiện gửi đi</p>
                <label v-for="event in events" :key="event.value" class="flex items-center gap-2 text-sm text-slate-400 py-0.5">
                    <input v-model="form.events" :value="event.value" type="checkbox">
                    <span>{{ event.label }} <code class="text-xs text-slate-500">{{ event.value }}</code></span>
                </label>
            </div>
            <button :disabled="form.processing" class="w-full px-4 py-2 bg-cyan-600 text-white rounded-lg text-sm font-medium disabled:opacity-50">Lưu</button>

            <div class="pt-3 border-t border-slate-800/60 space-y-2">
                <p class="text-sm text-slate-300">Secret ký: <span :class="endpoint.has_secret ? 'text-emerald-400' : 'text-amber-300'">{{ endpoint.has_secret ? 'Đã tạo' : 'Chưa có' }}</span></p>
                <p v-if="endpoint.secret_rotated_at" class="text-xs text-slate-500">Tạo/đổi lúc {{ formatDate(endpoint.secret_rotated_at) }}</p>
                <div class="flex gap-2">
                    <button type="button" @click="generateSecret" class="flex-1 px-3 py-2 border border-slate-700 text-slate-200 rounded-lg text-sm">{{ endpoint.has_secret ? 'Đổi secret' : 'Tạo secret' }}</button>
                    <button type="button" @click="sendTest" :disabled="busy || !endpoint.has_secret || !endpoint.url" class="flex-1 px-3 py-2 border border-slate-700 text-slate-200 rounded-lg text-sm disabled:opacity-40">{{ busy ? 'Đang gửi…' : 'Gửi thử' }}</button>
                </div>
            </div>
            <div class="text-xs text-slate-500 space-y-1 pt-2 border-t border-slate-800/60">
                <p>Mỗi lần gửi kèm header <code>X-Laptopplus-Event</code>, <code>X-Laptopplus-Delivery</code>, <code>X-Laptopplus-Timestamp</code> và <code>X-Laptopplus-Signature: sha256=…</code> (HMAC-SHA256 của <code>timestamp + "." + body</code> bằng secret).</p>
                <p>Bên nhận trả 2xx trong 10 giây là thành công. Nếu lỗi, hệ thống gửi lại sau {{ backoffText }}, sau đó ngừng.</p>
            </div>
        </form>

        <div class="lg:col-span-2 bg-slate-900 rounded-lg border border-slate-800/60 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-800/60 text-sm font-semibold text-slate-200">50 lần gửi gần nhất</div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800/40 text-sm">
                    <thead class="bg-slate-800/40"><tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Sự kiện</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Trạng thái</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Lần thử</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">HTTP</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Lỗi gần nhất</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Thời gian</th>
                        <th class="px-4 py-3"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-800/40">
                        <tr v-for="d in deliveries" :key="d.id">
                            <td class="px-4 py-3 text-slate-200"><code>{{ d.event }}</code></td>
                            <td class="px-4 py-3" :class="statusClass[d.status]">{{ statusLabel[d.status] || d.status }}<div v-if="d.status === 'failed' && d.next_attempt_at" class="text-xs text-slate-500">Thử lại {{ formatDate(d.next_attempt_at) }}</div></td>
                            <td class="px-4 py-3 text-slate-400">{{ d.attempt_count }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ d.response_status || '—' }}</td>
                            <td class="px-4 py-3 text-slate-400 max-w-xs truncate" :title="d.last_error">{{ d.last_error || '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ formatDate(d.sent_at || d.updated_at) }}</td>
                            <td class="px-4 py-3 text-right"><button v-if="['failed', 'dead'].includes(d.status) && d.event !== 'ping'" @click="retry(d)" class="text-cyan-400 hover:text-cyan-300">Gửi lại</button></td>
                        </tr>
                        <tr v-if="!deliveries.length"><td colspan="7" class="px-4 py-8 text-center text-slate-500">Chưa gửi webhook nào</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</AdminLayout>
</template>

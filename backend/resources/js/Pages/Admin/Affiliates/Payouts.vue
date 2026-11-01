<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ payouts: Object, totals: Object, affiliates: Array, filters: Object });
const flash = computed(() => usePage().props.flash);
const status = ref(props.filters?.status || '');
const affiliateId = ref(props.filters?.affiliate_id || '');
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');

const query = () => ({
    status: status.value || undefined,
    affiliate_id: affiliateId.value || undefined,
    from: from.value || undefined,
    to: to.value || undefined,
});
const exportUrl = computed(() => {
    const params = new URLSearchParams(Object.entries(query()).filter(([, v]) => v !== undefined));
    return `/admin/affiliates/payouts/export${params.toString() ? `?${params}` : ''}`;
});

function applyFilters() {
    router.get('/admin/affiliates/payouts', query(), { preserveState: true, replace: true });
}
function markPaid(p) {
    const note = prompt(`Đã chuyển ${formatPrice(p.amount)} cho ${p.affiliate?.label}? Ghi chú (mã giao dịch...) nếu có:`, '');
    if (note === null) return;
    router.post(`/admin/affiliates/payouts/${p.id}/paid`, { note }, { preserveScroll: true });
}
function cancel(p) {
    const reason = prompt('Lý do huỷ lệnh rút (số tiền sẽ trở lại số dư của CTV)');
    if (!reason) return;
    router.post(`/admin/affiliates/payouts/${p.id}/cancel`, { reason }, { preserveScroll: true });
}
function formatPrice(p) { return new Intl.NumberFormat('vi-VN').format(Number(p || 0)) + '₫'; }
function formatDate(d) { return d ? new Date(d).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' }) : '—'; }

const statusLabel = { pending: 'Chờ chuyển', paid: 'Đã rút', cancelled: 'Đã huỷ' };
const statusColor = { pending: 'bg-yellow-100 text-yellow-700', paid: 'bg-green-100 text-emerald-700', cancelled: 'bg-red-100 text-red-700' };
const sourceLabel = { schedule: 'Theo lịch rút', manual: 'CTV yêu cầu' };
</script>
<template>
<AdminLayout title="Lệnh rút tiền CTV">
    <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div v-if="flash?.error" class="mb-4 p-3 bg-red-500/10 border border-red-500/20 text-red-400 rounded-lg text-sm">{{ flash.error }}</div>
    <div class="flex flex-wrap justify-between items-start gap-3 mb-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-200">Lệnh rút tiền CTV</h3>
            <p class="text-xs text-slate-500">Lệnh tự tạo vào ngày rút CTV đã chọn (8h sáng) khi số dư được rút đạt mức tối thiểu, hoặc khi CTV bấm "Rút tiền". Chuyển khoản xong thì bấm "Đã chuyển".</p>
            <p class="text-sm text-slate-300 mt-2">Chờ chuyển: <b class="text-yellow-400">{{ formatPrice(totals.pending) }}</b> · Đã rút: <b class="text-emerald-400">{{ formatPrice(totals.paid) }}</b></p>
        </div>
        <a :href="exportUrl" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">Xuất Excel</a>
    </div>
    <div class="flex flex-wrap gap-2 mb-4">
        <select v-model="affiliateId" @change="applyFilters()" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
            <option value="">Tất cả CTV</option>
            <option v-for="a in affiliates" :key="a.id" :value="a.id">{{ a.label }}</option>
        </select>
        <select v-model="status" @change="applyFilters()" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
            <option value="">Tất cả trạng thái</option>
            <option v-for="(label, key) in statusLabel" :key="key" :value="key">{{ label }}</option>
        </select>
        <label class="flex items-center gap-1 text-sm text-slate-400">Từ <input v-model="from" type="date" @change="applyFilters()" class="border border-slate-700/50 rounded-lg px-2 py-1.5 text-sm"></label>
        <label class="flex items-center gap-1 text-sm text-slate-400">Đến <input v-model="to" type="date" @change="applyFilters()" class="border border-slate-700/50 rounded-lg px-2 py-1.5 text-sm"></label>
    </div>
    <div class="bg-slate-900 rounded-lg border border-slate-800/60 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800/40 text-sm">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Ngày tạo</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">CTV</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Tài khoản nhận</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Số tiền</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="p in payouts.data" :key="p.id" class="hover:bg-slate-800/40 align-top">
                    <td class="px-4 py-3 text-slate-300">{{ formatDate(p.created_at) }}<div class="text-xs text-slate-500">{{ sourceLabel[p.source] || p.source }}</div></td>
                    <td class="px-4 py-3"><Link v-if="p.affiliate" :href="`/admin/affiliates/${p.affiliate.id}`" class="text-cyan-500">{{ p.affiliate.label }}</Link><div class="text-xs text-slate-500">{{ p.affiliate?.phone }}</div></td>
                    <td class="px-4 py-3 text-slate-300">{{ p.bank_name }} – {{ p.bank_account_number }}<div class="text-xs text-slate-500">{{ p.bank_account_name }}</div></td>
                    <td class="px-4 py-3 text-right text-slate-200 font-medium">{{ formatPrice(p.amount) }}</td>
                    <td class="px-4 py-3 text-center">
                        <span :class="statusColor[p.status]" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ statusLabel[p.status] }}</span>
                        <div v-if="p.paid_at" class="text-xs text-slate-500 mt-1">{{ formatDate(p.paid_at) }}{{ p.paid_by ? ` · ${p.paid_by}` : '' }}</div>
                        <div v-if="p.note" class="text-xs text-slate-500 mt-1">{{ p.note }}</div>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap space-x-3">
                        <button v-if="p.status === 'pending'" type="button" @click="markPaid(p)" class="text-emerald-400 hover:text-emerald-300">Đã chuyển</button>
                        <button v-if="p.status === 'pending'" type="button" @click="cancel(p)" class="text-red-400 hover:text-red-300">Huỷ</button>
                    </td>
                </tr>
                <tr v-if="!payouts.data.length"><td colspan="6" class="px-4 py-8 text-center text-slate-500">Chưa có lệnh rút nào.</td></tr>
            </tbody>
        </table>
    </div>
    <div v-if="payouts.last_page > 1" class="flex justify-center gap-1 mt-4">
        <template v-for="link in payouts.links" :key="link.label">
            <Link v-if="link.url" :href="link.url" v-html="link.label" class="px-3 py-1 rounded text-sm" :class="link.active ? 'bg-cyan-600 text-white' : 'text-slate-400 hover:bg-slate-800'" preserve-scroll />
        </template>
    </div>
</AdminLayout>
</template>

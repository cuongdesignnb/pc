<script setup>
import { ref } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ commissions: Object, affiliates: Array, filters: Object });
const flash = usePage().props.flash;
const status = ref(props.filters?.status || '');
const affiliateId = ref(props.filters?.affiliate_id || '');
const expanded = ref(null);

function applyFilters() {
    router.get('/admin/affiliates/commissions', { status: status.value || undefined, affiliate_id: affiliateId.value || undefined }, { preserveState: true, replace: true });
}
function approve(c) {
    if (!confirm(`Xác nhận hoa hồng ${formatPrice(c.amount)}? CTV sẽ rút được ngay.`)) return;
    router.post(`/admin/affiliates/commissions/${c.id}/approve`, {}, { preserveScroll: true });
}
function cancel(c) {
    const reason = prompt('Lý do huỷ hoa hồng (trả hàng, bom hàng, nghi vấn...)');
    if (!reason) return;
    router.post(`/admin/affiliates/commissions/${c.id}/cancel`, { reason }, { preserveScroll: true });
}
function formatPrice(p) { return new Intl.NumberFormat('vi-VN').format(Number(p || 0)) + '₫'; }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('vi-VN') : '—'; }

const statusLabel = { pending: 'Chờ duyệt', approved: 'Được rút', cancelled: 'Đã huỷ' };
const statusColor = { pending: 'bg-yellow-100 text-yellow-700', approved: 'bg-green-100 text-emerald-700', cancelled: 'bg-red-100 text-red-700' };
const orderStatusLabel = { pending: 'Chờ xử lý', confirmed: 'Đã xác nhận', processing: 'Đang xử lý', shipping: 'Đang giao', delivered: 'Đã giao', cancelled: 'Đã hủy' };
</script>
<template>
<AdminLayout title="Hoa hồng CTV">
    <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-200">Hoa hồng CTV</h3>
            <p class="text-xs text-slate-500">Hoa hồng tự chuyển sang "Được rút" khi đơn giao đủ số ngày trong Cài đặt → Cộng tác viên, hoặc khi nhân viên bấm Xác nhận.</p>
        </div>
        <div class="flex gap-2">
            <select v-model="affiliateId" @change="applyFilters()" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <option value="">Tất cả CTV</option>
                <option v-for="a in affiliates" :key="a.id" :value="a.id">{{ a.label }}</option>
            </select>
            <select v-model="status" @change="applyFilters()" class="border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                <option value="">Tất cả trạng thái</option>
                <option v-for="(label, key) in statusLabel" :key="key" :value="key">{{ label }}</option>
            </select>
        </div>
    </div>
    <div class="bg-slate-900 rounded-lg border border-slate-800/60 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800/40 text-sm">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">CTV</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Đơn</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Giá trị</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Hoa hồng</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Dự kiến duyệt</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <template v-for="c in commissions.data" :key="c.id">
                    <tr class="hover:bg-slate-800/40">
                        <td class="px-4 py-3"><Link v-if="c.affiliate" :href="`/admin/affiliates/${c.affiliate.id}`" class="text-cyan-500">{{ c.affiliate.label }}</Link></td>
                        <td class="px-4 py-3">
                            <template v-if="c.order"><Link :href="`/admin/orders/${c.order.id}`" class="text-cyan-500">{{ c.order.number }}</Link> <span class="text-xs text-slate-500">{{ orderStatusLabel[c.order.status] || c.order.status }}</span></template>
                            <span v-else class="text-slate-300">Ngoài web {{ c.external?.code || '' }} <span class="text-xs text-slate-500">{{ c.external?.phone }}</span></span>
                        </td>
                        <td class="px-4 py-3 text-right text-slate-300">{{ formatPrice(c.base_amount) }}</td>
                        <td class="px-4 py-3 text-right text-slate-200 font-medium">
                            <button type="button" class="underline decoration-dotted" @click="expanded = expanded === c.id ? null : c.id">{{ formatPrice(c.amount) }}</button>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span :class="statusColor[c.status]" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ statusLabel[c.status] }}</span>
                            <div v-if="c.cancelled_reason" class="text-xs text-slate-500 mt-1">{{ c.cancelled_reason }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-400">{{ c.status === 'pending' ? (c.approve_after ? formatDate(c.approve_after) : 'Chờ giao hàng') : formatDate(c.approved_at) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap space-x-3">
                            <button v-if="c.status === 'pending'" type="button" @click="approve(c)" class="text-emerald-400 hover:text-emerald-300">Xác nhận hoa hồng</button>
                            <button v-if="c.status !== 'cancelled'" type="button" @click="cancel(c)" class="text-red-400 hover:text-red-300">Huỷ</button>
                        </td>
                    </tr>
                    <tr v-if="expanded === c.id" class="bg-slate-950/50">
                        <td colspan="7" class="px-6 py-3">
                            <div v-for="(line, i) in c.breakdown" :key="i" class="flex justify-between text-xs text-slate-400 py-0.5">
                                <span>{{ line.product_name }} × {{ line.quantity }} — {{ formatPrice(line.line_total) }}</span>
                                <span>{{ line.rule || 'Chưa đặt mức hoa hồng' }} → <b class="text-slate-200">{{ formatPrice(line.amount) }}</b></span>
                            </div>
                        </td>
                    </tr>
                </template>
                <tr v-if="!commissions.data?.length"><td colspan="7" class="px-4 py-8 text-center text-slate-500">Chưa có hoa hồng</td></tr>
            </tbody>
        </table>
    </div>
    <div v-if="commissions.last_page > 1" class="mt-4 flex justify-end gap-1">
        <button v-for="link in commissions.links" :key="link.label" @click="link.url && router.get(link.url, {}, { preserveState: true })"
            :disabled="!link.url" :class="link.active ? 'bg-cyan-600 text-white' : 'bg-slate-900 text-slate-300 hover:bg-slate-800/40'"
            class="px-3 py-1.5 text-sm border border-slate-700/50 rounded disabled:opacity-40" v-html="link.label"/>
    </div>
</AdminLayout>
</template>

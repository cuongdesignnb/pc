<script setup>
import { reactive } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    affiliate: Object, wallet: Object, customers: Array, commissions: Array,
    clicks: Array, clickCount: Number, otherAffiliates: Array,
});
const flash = usePage().props.flash;
const moveTo = reactive({});

function setStatus(value) {
    router.patch(`/admin/affiliates/${props.affiliate.id}/status`, { status: value }, { preserveScroll: true });
}
function reassign(customer) {
    const target = moveTo[customer.id];
    if (!target) return;
    if (!confirm(`Chuyển khách ${customer.phone} sang CTV khác? Các đơn sau của khách sẽ tính cho CTV mới.`)) return;
    router.patch(`/admin/affiliates/customers/${customer.id}`, { affiliate_id: target }, { preserveScroll: true });
}
function formatPrice(p) { return new Intl.NumberFormat('vi-VN').format(Number(p || 0)) + '₫'; }
function formatDate(d) { return d ? new Date(d).toLocaleString('vi-VN') : '—'; }

const statusLabel = { pending: 'Chờ duyệt', active: 'Đang hoạt động', locked: 'Đã khoá' };
const commissionLabel = { pending: 'Chờ duyệt', approved: 'Được rút', cancelled: 'Đã huỷ' };
const commissionColor = { pending: 'text-yellow-400', approved: 'text-emerald-400', cancelled: 'text-red-400' };
const sourceLabel = { link: 'Bấm link', code: 'Nhập mã', admin: 'Admin chuyển' };
</script>
<template>
<AdminLayout :title="`CTV ${affiliate.code || ''}`">
    <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <div class="mb-4"><Link href="/admin/affiliates" class="text-sm text-cyan-500 hover:text-cyan-400">← Danh sách CTV</Link></div>

    <div class="grid gap-4 lg:grid-cols-3 mb-6">
        <div class="bg-slate-900 rounded-lg border border-slate-800/60 p-4 lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-slate-200">{{ affiliate.user?.name }} <span class="text-cyan-500">{{ affiliate.code }}</span></h3>
                    <p class="text-sm text-slate-400">{{ statusLabel[affiliate.status] }} · Đăng ký {{ formatDate(affiliate.created_at) }}</p>
                </div>
                <div class="flex gap-2">
                    <button v-if="affiliate.status !== 'active'" type="button" @click="setStatus('active')" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-sm">{{ affiliate.status === 'pending' ? 'Duyệt CTV' : 'Mở khoá' }}</button>
                    <button v-else type="button" @click="setStatus('locked')" class="px-3 py-1.5 rounded-lg bg-red-600/80 text-white text-sm">Khoá</button>
                </div>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm md:grid-cols-3">
                <div><dt class="text-slate-500">SĐT</dt><dd class="text-slate-200">{{ affiliate.phone }}</dd></div>
                <div><dt class="text-slate-500">Email</dt><dd class="text-slate-200">{{ affiliate.user?.email }}</dd></div>
                <div><dt class="text-slate-500">Ngân hàng</dt><dd class="text-slate-200">{{ affiliate.bank_name || '—' }}</dd></div>
                <div><dt class="text-slate-500">Số tài khoản</dt><dd class="text-slate-200">{{ affiliate.bank_account_number || '—' }}</dd></div>
                <div><dt class="text-slate-500">Chủ tài khoản</dt><dd class="text-slate-200">{{ affiliate.bank_account_name || '—' }}</dd></div>
                <div><dt class="text-slate-500">Ngày rút tiền</dt><dd class="text-slate-200">{{ affiliate.payout_days?.length ? affiliate.payout_days.map(d => `ngày ${d}`).join(', ') : 'Chưa cài' }}</dd></div>
                <div v-if="affiliate.id_card_number"><dt class="text-slate-500">CCCD</dt><dd class="text-slate-200">{{ affiliate.id_card_number }}</dd></div>
            </dl>
        </div>
        <div class="bg-slate-900 rounded-lg border border-slate-800/60 p-4 space-y-3 text-sm">
            <div class="flex justify-between"><span class="text-slate-400">Số dư được rút</span><span class="text-emerald-400 font-semibold">{{ formatPrice(wallet.available) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-400">Hoa hồng đang chờ</span><span class="text-yellow-400">{{ formatPrice(wallet.pending) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-400">Khách đã gắn</span><span class="text-slate-200">{{ customers.length }}</span></div>
            <div class="flex justify-between"><span class="text-slate-400">Lượt bấm link</span><span class="text-slate-200">{{ clickCount }}</span></div>
            <Link :href="`/admin/affiliates/commissions?affiliate_id=${affiliate.id}`" class="block text-cyan-500 hover:text-cyan-400">Xem tất cả hoa hồng →</Link>
            <Link :href="`/admin/orders?affiliate_id=${affiliate.id}`" class="block text-cyan-500 hover:text-cyan-400">Xem đơn hàng của CTV →</Link>
        </div>
    </div>

    <div class="bg-slate-900 rounded-lg border border-slate-800/60 mb-6 overflow-x-auto">
        <h4 class="px-4 pt-4 text-sm font-semibold text-slate-200">Hoa hồng gần đây</h4>
        <table class="min-w-full divide-y divide-slate-800/40 mt-2 text-sm">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase">Đơn</th>
                <th class="px-4 py-2 text-right text-xs text-slate-400 uppercase">Giá trị</th>
                <th class="px-4 py-2 text-right text-xs text-slate-400 uppercase">Hoa hồng</th>
                <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase">Trạng thái</th>
                <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase">Ngày</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="c in commissions" :key="c.id">
                    <td class="px-4 py-2"><Link v-if="c.order" :href="`/admin/orders/${c.order.id}`" class="text-cyan-500">{{ c.order.order_number }}</Link><span v-else class="text-slate-300">Đơn ngoài web</span></td>
                    <td class="px-4 py-2 text-right text-slate-300">{{ formatPrice(c.base_amount) }}</td>
                    <td class="px-4 py-2 text-right text-slate-200 font-medium">{{ formatPrice(c.amount) }}</td>
                    <td class="px-4 py-2" :class="commissionColor[c.status]">{{ commissionLabel[c.status] }}<span v-if="c.cancelled_reason" class="text-slate-500"> · {{ c.cancelled_reason }}</span></td>
                    <td class="px-4 py-2 text-slate-400">{{ formatDate(c.created_at) }}</td>
                </tr>
                <tr v-if="!commissions.length"><td colspan="5" class="px-4 py-6 text-center text-slate-500">Chưa có hoa hồng</td></tr>
            </tbody>
        </table>
    </div>

    <div class="bg-slate-900 rounded-lg border border-slate-800/60 mb-6 overflow-x-auto">
        <h4 class="px-4 pt-4 text-sm font-semibold text-slate-200">Khách đã gắn với CTV</h4>
        <p class="px-4 text-xs text-slate-500">Khách thuộc CTV gửi link đầu tiên. Chỉ admin được chuyển khách sang CTV khác.</p>
        <table class="min-w-full divide-y divide-slate-800/40 mt-2 text-sm">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase">SĐT</th>
                <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase">Nguồn</th>
                <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase">Ngày gắn</th>
                <th class="px-4 py-2 text-right text-xs text-slate-400 uppercase">Chuyển sang CTV</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="c in customers" :key="c.id">
                    <td class="px-4 py-2 text-slate-200">{{ c.phone }}</td>
                    <td class="px-4 py-2 text-slate-400">{{ sourceLabel[c.source] || c.source }}</td>
                    <td class="px-4 py-2 text-slate-400">{{ formatDate(c.created_at) }}</td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        <select v-model="moveTo[c.id]" class="border border-slate-700/50 rounded-lg px-2 py-1 text-sm">
                            <option value="">— Chọn CTV —</option>
                            <option v-for="o in otherAffiliates" :key="o.id" :value="o.id">{{ o.label }}</option>
                        </select>
                        <button type="button" @click="reassign(c)" :disabled="!moveTo[c.id]" class="ml-2 text-cyan-500 disabled:opacity-40">Chuyển</button>
                    </td>
                </tr>
                <tr v-if="!customers.length"><td colspan="4" class="px-4 py-6 text-center text-slate-500">Chưa có khách</td></tr>
            </tbody>
        </table>
    </div>

    <div class="bg-slate-900 rounded-lg border border-slate-800/60 overflow-x-auto">
        <h4 class="px-4 pt-4 text-sm font-semibold text-slate-200">Lượt bấm link gần đây</h4>
        <table class="min-w-full divide-y divide-slate-800/40 mt-2 text-sm">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase">Thời gian</th>
                <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase">IP</th>
                <th class="px-4 py-2 text-left text-xs text-slate-400 uppercase">Trang</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="c in clicks" :key="c.id">
                    <td class="px-4 py-2 text-slate-400">{{ formatDate(c.created_at) }}</td>
                    <td class="px-4 py-2 text-slate-300">{{ c.ip }}</td>
                    <td class="px-4 py-2 text-slate-400 break-all">{{ c.landing_url }}</td>
                </tr>
                <tr v-if="!clicks.length"><td colspan="3" class="px-4 py-6 text-center text-slate-500">Chưa có lượt bấm</td></tr>
            </tbody>
        </table>
    </div>
</AdminLayout>
</template>

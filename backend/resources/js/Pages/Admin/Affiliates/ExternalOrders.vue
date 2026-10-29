<script setup>
import { useForm, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ orders: Object, affiliates: Array, categories: Array });
const flash = usePage().props.flash;
const form = useForm({
    customer_phone: '', affiliate_code: '', kiot_invoice_code: '', category_id: '',
    amount: '', sold_at: new Date().toISOString().slice(0, 10), note: '',
});

function submit() {
    form.post('/admin/affiliates/external-orders', {
        preserveScroll: true,
        onSuccess: () => form.reset('customer_phone', 'affiliate_code', 'kiot_invoice_code', 'amount', 'note'),
    });
}
function categoryLabel(category) {
    const parent = props.categories.find(c => c.id === category.parent_id);
    return parent ? `${parent.name} › ${category.name}` : category.name;
}
function formatPrice(p) { return new Intl.NumberFormat('vi-VN').format(Number(p || 0)) + '₫'; }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('vi-VN') : '—'; }
const statusLabel = { pending: 'Chờ duyệt', approved: 'Được rút', cancelled: 'Đã huỷ' };
</script>
<template>
<AdminLayout title="Đơn ngoài web">
    <div v-if="flash?.success" class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm">{{ flash.success }}</div>
    <form @submit.prevent="submit" class="bg-slate-900 rounded-lg border border-slate-800/60 p-4 mb-6">
        <h3 class="text-base font-semibold text-slate-200">Ghi nhận đơn qua điện thoại / tại cửa hàng</h3>
        <p class="text-xs text-slate-500 mb-4">Nếu SĐT đã thuộc một CTV thì hoa hồng tính cho CTV đó. Nếu chưa, nhập mã CTV khách đọc khi mua; SĐT sẽ được gắn vĩnh viễn với CTV này.</p>
        <div class="grid gap-3 md:grid-cols-3">
            <div><label class="block text-sm text-slate-300 mb-1">SĐT khách *</label><input v-model="form.customer_phone" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><p v-if="form.errors.customer_phone" class="text-red-400 text-xs mt-1">{{ form.errors.customer_phone }}</p></div>
            <div><label class="block text-sm text-slate-300 mb-1">Mã CTV (nếu khách mới)</label><input v-model="form.affiliate_code" placeholder="CTV001" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm uppercase"><p v-if="form.errors.affiliate_code" class="text-red-400 text-xs mt-1">{{ form.errors.affiliate_code }}</p></div>
            <div><label class="block text-sm text-slate-300 mb-1">Mã hoá đơn KIOT</label><input v-model="form.kiot_invoice_code" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="block text-sm text-slate-300 mb-1">Nhóm hàng *</label>
                <select v-model="form.category_id" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm">
                    <option value="">— Chọn danh mục —</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ categoryLabel(c) }}</option>
                </select>
                <p v-if="form.errors.category_id" class="text-red-400 text-xs mt-1">{{ form.errors.category_id }}</p>
            </div>
            <div><label class="block text-sm text-slate-300 mb-1">Số tiền khách trả (đ) *</label><input v-model="form.amount" type="number" min="1" step="1000" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"><p v-if="form.errors.amount" class="text-red-400 text-xs mt-1">{{ form.errors.amount }}</p></div>
            <div><label class="block text-sm text-slate-300 mb-1">Ngày bán *</label><input v-model="form.sold_at" type="date" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
            <div class="md:col-span-2"><label class="block text-sm text-slate-300 mb-1">Ghi chú</label><input v-model="form.note" class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"></div>
            <div class="flex items-end"><button type="submit" :disabled="form.processing" class="w-full px-4 py-2 rounded-lg bg-cyan-600 text-white text-sm disabled:opacity-50">Ghi nhận</button></div>
        </div>
        <p class="text-xs text-slate-500 mt-3">Hoa hồng tính theo mức của danh mục đã chọn, ở trạng thái "Chờ duyệt" và tự được duyệt sau số ngày trong Cài đặt (tính từ ngày bán).</p>
    </form>

    <div class="bg-slate-900 rounded-lg border border-slate-800/60 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800/40 text-sm">
            <thead class="bg-slate-800/40"><tr>
                <th class="px-4 py-3 text-left text-xs text-slate-400 uppercase">Ngày bán</th>
                <th class="px-4 py-3 text-left text-xs text-slate-400 uppercase">CTV</th>
                <th class="px-4 py-3 text-left text-xs text-slate-400 uppercase">SĐT khách</th>
                <th class="px-4 py-3 text-left text-xs text-slate-400 uppercase">Hoá đơn KIOT</th>
                <th class="px-4 py-3 text-left text-xs text-slate-400 uppercase">Nhóm hàng</th>
                <th class="px-4 py-3 text-right text-xs text-slate-400 uppercase">Số tiền</th>
                <th class="px-4 py-3 text-right text-xs text-slate-400 uppercase">Hoa hồng</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-800/40">
                <tr v-for="o in orders.data" :key="o.id">
                    <td class="px-4 py-3 text-slate-400">{{ formatDate(o.sold_at) }}</td>
                    <td class="px-4 py-3 text-slate-200">{{ o.affiliate?.user?.name }} <span class="text-cyan-500">{{ o.affiliate?.code }}</span></td>
                    <td class="px-4 py-3 text-slate-300">{{ o.customer_phone }}</td>
                    <td class="px-4 py-3 text-slate-300">{{ o.kiot_invoice_code || '—' }}</td>
                    <td class="px-4 py-3 text-slate-400">{{ o.category?.name }}</td>
                    <td class="px-4 py-3 text-right text-slate-300">{{ formatPrice(o.amount) }}</td>
                    <td class="px-4 py-3 text-right text-slate-200">{{ formatPrice(o.commission?.amount) }} <div class="text-xs text-slate-500">{{ statusLabel[o.commission?.status] }}</div></td>
                </tr>
                <tr v-if="!orders.data?.length"><td colspan="7" class="px-4 py-8 text-center text-slate-500">Chưa có đơn ngoài web</td></tr>
            </tbody>
        </table>
    </div>
    <div v-if="orders.last_page > 1" class="mt-4 flex justify-end gap-1">
        <button v-for="link in orders.links" :key="link.label" @click="link.url && router.get(link.url, {}, { preserveState: true })"
            :disabled="!link.url" :class="link.active ? 'bg-cyan-600 text-white' : 'bg-slate-900 text-slate-300 hover:bg-slate-800/40'"
            class="px-3 py-1.5 text-sm border border-slate-700/50 rounded disabled:opacity-40" v-html="link.label"/>
    </div>
</AdminLayout>
</template>

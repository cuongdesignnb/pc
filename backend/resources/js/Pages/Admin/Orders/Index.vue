<script setup>
import { ref, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ orders: Object, filters: Object, statuses: Array, paymentStatuses: Array });
const flash = usePage().props.flash;
const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const paymentStatus = ref(props.filters?.payment_status || '');

let timer;
watch(search, () => { clearTimeout(timer); timer = setTimeout(applyFilters, 400); });
function applyFilters() {
    router.get('/admin/orders', { search: search.value || undefined, status: status.value || undefined, payment_status: paymentStatus.value || undefined }, { preserveState: true, replace: true });
}
function formatPrice(p) { return new Intl.NumberFormat('vi-VN').format(p) + '₫'; }
function formatDate(d) { return new Date(d).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }

const statusLabel = { pending: 'Chờ xử lý', confirmed: 'Đã xác nhận', shipping: 'Đang giao', delivered: 'Đã giao', cancelled: 'Đã hủy' };
const statusColor = { pending: 'bg-yellow-100 text-yellow-700', confirmed: 'bg-blue-100 text-blue-700', shipping: 'bg-purple-100 text-purple-700', delivered: 'bg-green-100 text-green-700', cancelled: 'bg-red-100 text-red-700' };
const payLabel = { pending: 'Chưa TT', paid: 'Đã TT', failed: 'Thất bại', refunded: 'Hoàn tiền' };
const payColor = { pending: 'bg-yellow-100 text-yellow-700', paid: 'bg-green-100 text-green-700', failed: 'bg-red-100 text-red-700', refunded: 'bg-gray-100 text-gray-600' };
</script>
<template>
<AdminLayout title="Đơn hàng">
    <div v-if="flash?.success" class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ flash.success }}</div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <input v-model="search" placeholder="Mã đơn, tên KH, SĐT..." class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-56 focus:ring-2 focus:ring-indigo-500">
            <select v-model="status" @change="applyFilters()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Tất cả trạng thái</option>
                <option v-for="s in statuses" :value="s">{{ statusLabel[s] || s }}</option>
            </select>
            <select v-model="paymentStatus" @change="applyFilters()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Thanh toán</option>
                <option v-for="s in paymentStatuses" :value="s">{{ payLabel[s] || s }}</option>
            </select>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mã đơn</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Khách hàng</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tổng tiền</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Thanh toán</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ngày đặt</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                <tr v-for="o in orders.data" :key="o.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-indigo-600">{{ o.order_number || o.order_code }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ o.shipping_name || o.customer_name || o.user?.name || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900 text-right font-medium">{{ formatPrice(o.total) }}</td>
                    <td class="px-4 py-3 text-center"><span :class="statusColor[o.order_status || o.status] || 'bg-gray-100 text-gray-600'" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ statusLabel[o.order_status || o.status] || o.order_status || o.status }}</span></td>
                    <td class="px-4 py-3 text-center"><span :class="payColor[o.payment_status] || 'bg-gray-100 text-gray-600'" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ payLabel[o.payment_status] || o.payment_status }}</span></td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(o.created_at) }}</td>
                    <td class="px-4 py-3 text-right">
                        <Link :href="`/admin/orders/${o.id}`" class="text-indigo-600 hover:text-indigo-800 text-sm">Chi tiết</Link>
                    </td>
                </tr>
                <tr v-if="!orders.data?.length"><td colspan="7" class="px-4 py-8 text-center text-gray-400">Chưa có đơn hàng</td></tr>
            </tbody>
        </table>
    </div>
    <div v-if="orders.last_page > 1" class="mt-4 flex justify-end gap-1">
        <button v-for="link in orders.links" :key="link.label" @click="link.url && router.get(link.url, {}, {preserveState:true})"
            :disabled="!link.url" :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
            class="px-3 py-1.5 text-sm border border-gray-300 rounded disabled:opacity-40" v-html="link.label"/>
    </div>
</AdminLayout>
</template>

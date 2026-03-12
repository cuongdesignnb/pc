<script setup>
import { ref } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ order: Object });
const flash = usePage().props.flash;

const newStatus = ref(props.order.order_status || props.order.status || 'pending');
const newPayStatus = ref(props.order.payment_status || 'pending');

function updateStatus() { router.patch(`/admin/orders/${props.order.id}/status`, { order_status: newStatus.value }); }
function updatePayment() { router.patch(`/admin/orders/${props.order.id}/payment-status`, { payment_status: newPayStatus.value }); }
function formatPrice(p) { return new Intl.NumberFormat('vi-VN').format(p) + '₫'; }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'; }

const statusLabel = { pending: 'Chờ xử lý', confirmed: 'Đã xác nhận', shipping: 'Đang giao', delivered: 'Đã giao', cancelled: 'Đã hủy' };
const payLabel = { pending: 'Chưa thanh toán', paid: 'Đã thanh toán', failed: 'Thất bại', refunded: 'Hoàn tiền' };
</script>
<template>
<AdminLayout title="Chi tiết đơn hàng">
    <div v-if="flash?.success" class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ flash.success }}</div>
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-gray-900">Đơn #{{ order.order_number || order.order_code }}</h3>
        <Link href="/admin/orders" class="text-sm text-gray-500 hover:text-gray-700">← Quay lại</Link>
    </div>
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Thông tin KH</h4>
            <div class="space-y-1 text-sm text-gray-600">
                <p><span class="text-gray-400">Tên:</span> {{ order.shipping_name || order.customer_name || order.user?.name }}</p>
                <p><span class="text-gray-400">SĐT:</span> {{ order.shipping_phone || order.customer_phone || '—' }}</p>
                <p><span class="text-gray-400">Địa chỉ:</span> {{ [order.shipping_address, order.shipping_ward, order.shipping_district, order.shipping_city].filter(Boolean).join(', ') || '—' }}</p>
                <p v-if="order.notes"><span class="text-gray-400">Ghi chú:</span> {{ order.notes }}</p>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Trạng thái đơn</h4>
            <div class="space-y-3">
                <select v-model="newStatus" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option v-for="(l, k) in statusLabel" :value="k">{{ l }}</option>
                </select>
                <button @click="updateStatus" class="w-full px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Cập nhật trạng thái</button>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">Thanh toán</h4>
            <div class="space-y-3">
                <select v-model="newPayStatus" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option v-for="(l, k) in payLabel" :value="k">{{ l }}</option>
                </select>
                <button @click="updatePayment" class="w-full px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">Cập nhật thanh toán</button>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-4">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sản phẩm</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Đơn giá</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">SL</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Thành tiền</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                <tr v-for="item in order.items" :key="item.id">
                    <td class="px-4 py-3 text-sm text-gray-900">{{ item.product?.name || item.product_name || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500 text-right">{{ formatPrice(item.price) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500 text-center">{{ item.quantity }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900 text-right font-medium">{{ formatPrice(item.price * item.quantity) }}</td>
                </tr>
            </tbody>
            <tfoot class="bg-gray-50">
                <tr v-if="order.subtotal"><td colspan="3" class="px-4 py-2 text-sm text-gray-500 text-right">Tạm tính</td><td class="px-4 py-2 text-sm text-right">{{ formatPrice(order.subtotal) }}</td></tr>
                <tr v-if="order.discount"><td colspan="3" class="px-4 py-2 text-sm text-gray-500 text-right">Giảm giá</td><td class="px-4 py-2 text-sm text-red-600 text-right">-{{ formatPrice(order.discount) }}</td></tr>
                <tr v-if="order.shipping_fee"><td colspan="3" class="px-4 py-2 text-sm text-gray-500 text-right">Phí ship</td><td class="px-4 py-2 text-sm text-right">{{ formatPrice(order.shipping_fee) }}</td></tr>
                <tr><td colspan="3" class="px-4 py-3 text-sm font-semibold text-gray-900 text-right">Tổng cộng</td><td class="px-4 py-3 text-base font-bold text-indigo-600 text-right">{{ formatPrice(order.total) }}</td></tr>
            </tfoot>
        </table>
    </div>
</AdminLayout>
</template>

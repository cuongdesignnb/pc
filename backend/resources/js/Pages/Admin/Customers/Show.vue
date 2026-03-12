<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const props = defineProps({ customer: Object });
const c = props.customer;
function formatDate(d) { return d ? new Date(d).toLocaleDateString('vi-VN') : '—'; }
function formatPrice(p) { return new Intl.NumberFormat('vi-VN').format(p) + '₫'; }
const statusLabel = { pending: 'Chờ xử lý', confirmed: 'Đã xác nhận', shipping: 'Đang giao', delivered: 'Đã giao', cancelled: 'Đã hủy' };
const statusColor = { pending: 'bg-yellow-100 text-yellow-700', confirmed: 'bg-blue-100 text-blue-700', shipping: 'bg-purple-100 text-purple-700', delivered: 'bg-green-100 text-green-700', cancelled: 'bg-red-100 text-red-700' };
</script>
<template>
<AdminLayout title="Chi tiết khách hàng">
    <div class="max-w-4xl">
        <div class="flex items-center justify-between mb-6"><h3 class="text-lg font-semibold text-gray-900">{{ c.name }}</h3><Link href="/admin/customers" class="text-sm text-gray-500 hover:text-gray-700">← Quay lại</Link></div>
        <!-- Info -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div><span class="text-gray-500 block">Email</span><span class="font-medium">{{ c.email }}</span></div>
                <div><span class="text-gray-500 block">SĐT</span><span class="font-medium">{{ c.phone || '—' }}</span></div>
                <div><span class="text-gray-500 block">Ngày đăng ký</span><span class="font-medium">{{ formatDate(c.created_at) }}</span></div>
                <div><span class="text-gray-500 block">Vai trò</span><span class="font-medium">{{ c.role }}</span></div>
            </div>
        </div>
        <!-- Orders -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h4 class="font-medium text-gray-900 mb-4">Đơn hàng ({{ c.orders?.length || 0 }})</h4>
            <table v-if="c.orders?.length" class="min-w-full divide-y divide-gray-200">
                <thead><tr>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Mã ĐH</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tổng</th>
                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ngày</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="o in c.orders" :key="o.id">
                        <td class="px-3 py-2 text-sm font-mono">{{ o.order_number }}</td>
                        <td class="px-3 py-2 text-sm text-right font-medium">{{ formatPrice(o.total) }}</td>
                        <td class="px-3 py-2 text-center"><span :class="statusColor[o.order_status || o.status] || 'bg-gray-100 text-gray-500'" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ statusLabel[o.order_status || o.status] || o.order_status || o.status }}</span></td>
                        <td class="px-3 py-2 text-sm text-gray-500">{{ formatDate(o.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-gray-400 text-sm">Chưa có đơn hàng</p>
        </div>
        <!-- Reviews -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h4 class="font-medium text-gray-900 mb-4">Đánh giá ({{ c.reviews?.length || 0 }})</h4>
            <div v-if="c.reviews?.length" class="space-y-3">
                <div v-for="r in c.reviews" :key="r.id" class="border border-gray-100 rounded-lg p-3">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-yellow-400">{{ '★'.repeat(r.rating) }}{{ '☆'.repeat(5 - r.rating) }}</span>
                        <span class="text-xs text-gray-400">{{ formatDate(r.created_at) }}</span>
                        <span :class="r.is_approved ? 'text-green-600' : 'text-yellow-600'" class="text-xs">{{ r.is_approved ? 'Đã duyệt' : 'Chờ duyệt' }}</span>
                    </div>
                    <p v-if="r.title" class="text-sm font-medium text-gray-900">{{ r.title }}</p>
                    <p class="text-sm text-gray-600">{{ r.body }}</p>
                </div>
            </div>
            <p v-else class="text-gray-400 text-sm">Chưa có đánh giá</p>
        </div>
    </div>
</AdminLayout>
</template>

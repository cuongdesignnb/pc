<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    stats: Object,
});

const fmtPrice = (v) => new Intl.NumberFormat('vi-VN').format(v || 0) + ' VND';
const fmtNum = (v) => new Intl.NumberFormat('vi-VN').format(v || 0);

const statusLabels = {
    pending: 'Chờ xử lý',
    confirmed: 'Đã xác nhận',
    processing: 'Đang xử lý',
    shipping: 'Đang giao',
    delivered: 'Đã giao',
    cancelled: 'Đã hủy',
};
const statusColors = {
    pending: 'bg-amber-500/15 text-amber-400 border-amber-500/20',
    confirmed: 'bg-blue-500/15 text-blue-400 border-blue-500/20',
    processing: 'bg-violet-500/15 text-violet-400 border-violet-500/20',
    shipping: 'bg-cyan-500/15 text-cyan-400 border-cyan-500/20',
    delivered: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/20',
    cancelled: 'bg-red-500/15 text-red-400 border-red-500/20',
};

const quickActions = [
    { name: 'Thêm sản phẩm', href: '/admin/products/create', icon: 'M12 4v16m8-8H4', color: 'from-cyan-500 to-blue-600' },
    { name: 'Xem đơn hàng', href: '/admin/orders', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', color: 'from-violet-500 to-purple-600' },
    { name: 'Viết bài mới', href: '/admin/posts/create', icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', color: 'from-emerald-500 to-teal-600' },
    { name: 'AI Bài viết', href: '/admin/ai-articles', icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z', color: 'from-amber-500 to-orange-600' },
];
</script>

<template>
    <AdminLayout title="Dashboard">
        <!-- Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <!-- Revenue -->
            <div class="relative overflow-hidden rounded-xl bg-slate-900 border border-slate-800/60 p-5 group hover:border-emerald-500/30 transition-colors">
                <div class="absolute top-0 right-0 w-24 h-24 bg-emerald-500/5 rounded-full -translate-y-6 translate-x-6"></div>
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Doanh thu hôm nay</span>
                </div>
                <p class="text-2xl font-bold text-white">{{ fmtPrice(stats?.revenue_today) }}</p>
            </div>

            <!-- Orders -->
            <div class="relative overflow-hidden rounded-xl bg-slate-900 border border-slate-800/60 p-5 group hover:border-blue-500/30 transition-colors">
                <div class="absolute top-0 right-0 w-24 h-24 bg-blue-500/5 rounded-full -translate-y-6 translate-x-6"></div>
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </div>
                    <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Đơn hàng mới</span>
                </div>
                <p class="text-2xl font-bold text-white">{{ fmtNum(stats?.orders_today) }}</p>
            </div>

            <!-- Products -->
            <div class="relative overflow-hidden rounded-xl bg-slate-900 border border-slate-800/60 p-5 group hover:border-violet-500/30 transition-colors">
                <div class="absolute top-0 right-0 w-24 h-24 bg-violet-500/5 rounded-full -translate-y-6 translate-x-6"></div>
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg bg-violet-500/10 border border-violet-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Tổng sản phẩm</span>
                </div>
                <p class="text-2xl font-bold text-white">{{ fmtNum(stats?.total_products) }}</p>
            </div>

            <!-- Customers -->
            <div class="relative overflow-hidden rounded-xl bg-slate-900 border border-slate-800/60 p-5 group hover:border-amber-500/30 transition-colors">
                <div class="absolute top-0 right-0 w-24 h-24 bg-amber-500/5 rounded-full -translate-y-6 translate-x-6"></div>
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Khách hàng mới</span>
                </div>
                <p class="text-2xl font-bold text-white">{{ fmtNum(stats?.new_customers) }}</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Recent Orders -->
            <div class="lg:col-span-2 bg-slate-900 rounded-xl border border-slate-800/60 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-800/60">
                    <h3 class="text-sm font-semibold text-slate-200">Đơn hàng gần đây</h3>
                    <Link href="/admin/orders" class="text-xs text-cyan-400 hover:text-cyan-300 transition-colors font-medium">Xem tất cả</Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-slate-800/40">
                                <th class="px-5 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Mã đơn</th>
                                <th class="px-5 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Khách hàng</th>
                                <th class="px-5 py-3 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Tổng tiền</th>
                                <th class="px-5 py-3 text-center text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Trạng thái</th>
                                <th class="px-5 py-3 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Ngày tạo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/30">
                            <tr v-for="order in stats?.recent_orders || []" :key="order.id" class="hover:bg-slate-800/30 transition-colors">
                                <td class="px-5 py-3">
                                    <Link :href="`/admin/orders/${order.id}`" class="text-sm font-medium text-cyan-400 hover:text-cyan-300">{{ order.order_code }}</Link>
                                </td>
                                <td class="px-5 py-3 text-sm text-slate-400">{{ order.customer_name }}</td>
                                <td class="px-5 py-3 text-sm text-slate-300 text-right font-medium tabular-nums">{{ fmtPrice(order.total) }}</td>
                                <td class="px-5 py-3 text-center">
                                    <span :class="[statusColors[order.status] || 'bg-slate-700 text-slate-400', 'inline-flex px-2.5 py-1 text-[11px] font-medium rounded-full border']">
                                        {{ statusLabels[order.status] || order.status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-sm text-slate-500 text-right tabular-nums">{{ order.created_at }}</td>
                            </tr>
                            <tr v-if="!stats?.recent_orders?.length">
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-600">Chưa có đơn hàng nào</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="space-y-4">
                <div class="bg-slate-900 rounded-xl border border-slate-800/60 p-5">
                    <h3 class="text-sm font-semibold text-slate-200 mb-4">Thao tác nhanh</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <Link
                            v-for="action in quickActions"
                            :key="action.name"
                            :href="action.href"
                            class="flex flex-col items-center gap-2 p-4 rounded-xl border border-slate-800/50 hover:border-cyan-500/30 bg-slate-800/30 hover:bg-slate-800/60 transition-all group"
                        >
                            <div :class="`w-10 h-10 rounded-lg bg-gradient-to-br ${action.color} flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform`">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="action.icon"/></svg>
                            </div>
                            <span class="text-[11px] font-medium text-slate-400 text-center group-hover:text-slate-200 transition-colors">{{ action.name }}</span>
                        </Link>
                    </div>
                </div>

                <!-- System Info -->
                <div class="bg-slate-900 rounded-xl border border-slate-800/60 p-5">
                    <h3 class="text-sm font-semibold text-slate-200 mb-4">Thông tin hệ thống</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-500">Tổng sản phẩm</span>
                            <span class="text-xs font-semibold text-slate-300">{{ fmtNum(stats?.total_products) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-500">Đơn hàng hôm nay</span>
                            <span class="text-xs font-semibold text-slate-300">{{ fmtNum(stats?.orders_today) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-500">Khách mới hôm nay</span>
                            <span class="text-xs font-semibold text-slate-300">{{ fmtNum(stats?.new_customers) }}</span>
                        </div>
                        <div class="h-px bg-slate-800/60 my-2"></div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-500">Phiên bản</span>
                            <span class="text-[10px] font-mono text-cyan-400/80 bg-cyan-400/5 px-1.5 py-0.5 rounded">v2.0.0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

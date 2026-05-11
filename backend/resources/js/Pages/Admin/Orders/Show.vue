<script setup>
import { ref } from "vue";
import { router, Link, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";

const props = defineProps({ order: Object });
const flash = usePage().props.flash;

const newStatus = ref(
    props.order.order_status || props.order.status || "pending",
);
const newPayStatus = ref(props.order.payment_status || "pending");

function updateStatus() {
    router.patch(`/admin/orders/${props.order.id}/status`, {
        order_status: newStatus.value,
    });
}
function updatePayment() {
    router.patch(`/admin/orders/${props.order.id}/payment-status`, {
        payment_status: newPayStatus.value,
    });
}
function formatPrice(p) {
    return new Intl.NumberFormat("vi-VN").format(p) + "₫";
}
function formatDate(d) {
    return d
        ? new Date(d).toLocaleDateString("vi-VN", {
              day: "2-digit",
              month: "2-digit",
              year: "numeric",
              hour: "2-digit",
              minute: "2-digit",
          })
        : "—";
}

const statusLabel = {
    pending: "Chờ xử lý",
    confirmed: "Đã xác nhận",
    shipping: "Đang giao",
    delivered: "Đã giao",
    cancelled: "Đã hủy",
};
const payLabel = {
    pending: "Chưa thanh toán",
    paid: "Đã thanh toán",
    failed: "Thất bại",
    refunded: "Hoàn tiền",
};
</script>
<template>
    <AdminLayout title="Chi tiết đơn hàng">
        <div
            v-if="flash?.success"
            class="mb-4 p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm"
        >
            {{ flash.success }}
        </div>
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-slate-200">
                Đơn #{{ order.order_number || order.order_code }}
            </h3>
            <Link
                href="/admin/orders"
                class="text-sm text-slate-400 hover:text-slate-300"
                >← Quay lại</Link
            >
        </div>
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div
                class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-4"
            >
                <h4 class="text-sm font-semibold text-slate-300 mb-3">
                    Thông tin KH
                </h4>
                <div class="space-y-1 text-sm text-slate-400">
                    <p>
                        <span class="text-slate-500">Tên:</span>
                        {{
                            order.shipping_name ||
                            order.customer_name ||
                            order.user?.name
                        }}
                    </p>
                    <p>
                        <span class="text-slate-500">SĐT:</span>
                        {{
                            order.shipping_phone || order.customer_phone || "—"
                        }}
                    </p>
                    <p>
                        <span class="text-slate-500">Địa chỉ:</span>
                        {{
                            [
                                order.shipping_address,
                                order.shipping_ward,
                                order.shipping_district,
                                order.shipping_city,
                            ]
                                .filter(Boolean)
                                .join(", ") || "—"
                        }}
                    </p>
                    <p v-if="order.notes">
                        <span class="text-slate-500">Ghi chú:</span>
                        {{ order.notes }}
                    </p>
                </div>
            </div>
            <div
                class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-4"
            >
                <h4 class="text-sm font-semibold text-slate-300 mb-3">
                    Trạng thái đơn
                </h4>
                <div class="space-y-3">
                    <select
                        v-model="newStatus"
                        class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"
                    >
                        <option v-for="(l, k) in statusLabel" :value="k">
                            {{ l }}
                        </option>
                    </select>
                    <button
                        @click="updateStatus"
                        class="w-full px-3 py-2 bg-cyan-600 text-white rounded-lg text-sm hover:bg-cyan-700"
                    >
                        Cập nhật trạng thái
                    </button>
                </div>
            </div>
            <div
                class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 p-4"
            >
                <h4 class="text-sm font-semibold text-slate-300 mb-3">
                    Thanh toán
                </h4>
                <div class="space-y-3">
                    <select
                        v-model="newPayStatus"
                        class="w-full border border-slate-700/50 rounded-lg px-3 py-2 text-sm"
                    >
                        <option v-for="(l, k) in payLabel" :value="k">
                            {{ l }}
                        </option>
                    </select>
                    <button
                        @click="updatePayment"
                        class="w-full px-3 py-2 bg-cyan-600 text-white rounded-lg text-sm hover:bg-cyan-700"
                    >
                        Cập nhật thanh toán
                    </button>
                </div>
            </div>
        </div>
        <div
            class="bg-slate-900 rounded-lg shadow-none border border-slate-800/60 overflow-hidden mb-4"
        >
            <table class="min-w-full divide-y divide-slate-800/40">
                <thead class="bg-slate-800/40">
                    <tr>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase"
                        >
                            Sản phẩm
                        </th>
                        <th
                            class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase"
                        >
                            Đơn giá
                        </th>
                        <th
                            class="px-4 py-3 text-center text-xs font-medium text-slate-400 uppercase"
                        >
                            SL
                        </th>
                        <th
                            class="px-4 py-3 text-right text-xs font-medium text-slate-400 uppercase"
                        >
                            Thành tiền
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/40">
                    <tr v-for="item in order.items" :key="item.id">
                        <td class="px-4 py-3 text-sm text-slate-200">
                            {{ item.product?.name || item.product_name || "—" }}
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-400 text-right">
                            {{ formatPrice(item.price) }}
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-400 text-center">
                            {{ item.quantity }}
                        </td>
                        <td
                            class="px-4 py-3 text-sm text-slate-200 text-right font-medium"
                        >
                            {{ formatPrice(item.price * item.quantity) }}
                        </td>
                    </tr>
                </tbody>
                <tfoot class="bg-slate-800/40">
                    <tr v-if="order.subtotal">
                        <td
                            colspan="3"
                            class="px-4 py-2 text-sm text-slate-400 text-right"
                        >
                            Tạm tính
                        </td>
                        <td class="px-4 py-2 text-sm text-right">
                            {{ formatPrice(order.subtotal) }}
                        </td>
                    </tr>
                    <tr v-if="order.discount">
                        <td
                            colspan="3"
                            class="px-4 py-2 text-sm text-slate-400 text-right"
                        >
                            Giảm giá
                        </td>
                        <td class="px-4 py-2 text-sm text-red-600 text-right">
                            -{{ formatPrice(order.discount) }}
                        </td>
                    </tr>
                    <tr v-if="order.shipping_fee">
                        <td
                            colspan="3"
                            class="px-4 py-2 text-sm text-slate-400 text-right"
                        >
                            Phí ship
                        </td>
                        <td class="px-4 py-2 text-sm text-right">
                            {{ formatPrice(order.shipping_fee) }}
                        </td>
                    </tr>
                    <tr>
                        <td
                            colspan="3"
                            class="px-4 py-3 text-sm font-semibold text-slate-200 text-right"
                        >
                            Tổng cộng
                        </td>
                        <td
                            class="px-4 py-3 text-base font-bold text-cyan-500 text-right"
                        >
                            {{ formatPrice(order.total) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </AdminLayout>
</template>

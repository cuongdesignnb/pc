<script setup>
import { ref, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const props = defineProps({ customers: Object, filters: Object });
const flash = usePage().props.flash;
const search = ref(props.filters?.search || '');

let timer;
watch(search, () => { clearTimeout(timer); timer = setTimeout(() => router.get('/admin/customers', { search: search.value || undefined }, { preserveState: true, replace: true }), 400); });

function formatDate(d) { return d ? new Date(d).toLocaleDateString('vi-VN') : '—'; }
function formatPrice(p) { return new Intl.NumberFormat('vi-VN').format(p) + '₫'; }
</script>
<template>
<AdminLayout title="Khách hàng">
    <div v-if="flash?.success" class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ flash.success }}</div>
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Khách hàng</h3>
        <input v-model="search" placeholder="Tìm tên, email, SĐT..." class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-56">
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Khách hàng</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">SĐT</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Đơn hàng</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Đánh giá</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ngày ĐK</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Thao tác</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                <tr v-for="c in customers.data" :key="c.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-sm font-medium">{{ (c.name||'?')[0] }}</div>
                            <span class="text-sm font-medium text-gray-900">{{ c.name }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ c.email }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ c.phone || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500 text-center">{{ c.orders_count || 0 }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500 text-center">{{ c.reviews_count || 0 }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ formatDate(c.created_at) }}</td>
                    <td class="px-4 py-3 text-right">
                        <Link :href="`/admin/customers/${c.id}`" class="text-indigo-600 hover:text-indigo-800 text-sm">Chi tiết</Link>
                    </td>
                </tr>
                <tr v-if="!customers.data?.length"><td colspan="7" class="px-4 py-8 text-center text-gray-400">Không tìm thấy khách hàng</td></tr>
            </tbody>
        </table>
    </div>
    <!-- Pagination -->
    <div v-if="customers.last_page > 1" class="mt-4 flex justify-center gap-1">
        <template v-for="link in customers.links" :key="link.label">
            <button v-if="link.url" @click="router.get(link.url, {}, { preserveState: true })" :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'" class="px-3 py-1 border rounded text-sm" v-html="link.label"></button>
            <span v-else class="px-3 py-1 text-sm text-gray-400" v-html="link.label"></span>
        </template>
    </div>
</AdminLayout>
</template>
